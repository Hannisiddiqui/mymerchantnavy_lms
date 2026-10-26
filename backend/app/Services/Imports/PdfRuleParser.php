<?php

namespace App\Services\Imports;

use App\Services\Pdf\PdfTool;

/**
 * Reads multiple-choice questions from a well-structured PDF WITHOUT AI: instant, free and exact.
 *
 * What it understands (one block per question):
 *     Q72  ·  Part 3  ·  Calculation  ·  Level E  ·  MCQ      ← optional header line
 *     Q72.  The value of ∛729 is:                              ← question (may wrap over several lines)
 *     (a)  27   (b)  81                                         ← options, one per line OR two per line (columns)
 *     (c)  243  (d)  9
 *     Answer: (d)  9
 *     Given: …  Rule: …  Working: …  Result: …                 ← explanation parts (labels listed in LABELS)
 *   …and an "Answer key" page at the end, used to double-check every answer.
 *
 * Text is rebuilt from glyph positions by tools/pdf_tool.py, so symbols such as √ ∛ ² π × ÷ stay where they
 * are on the page. If a PDF does not follow this layout, use the AI reader instead.
 */
class PdfRuleParser implements QuestionParser
{
    /**
     * Explanation labels (lower case) → shown to students? Add your own labels here.
     * Labels not listed are treated as ordinary text of the previous part.
     */
    private const LABELS = [
        'given' => true, 'rule' => true, 'formula' => true, 'working' => true, 'result' => true, 'check' => true,
        'why the other options fail' => true, 'why the others are wrong' => true, 'why the others fail' => true, 'concept behind the question' => true,
        'what the question is asking' => true, 'step by step' => true, 'why the other options are wrong' => true,
        'explanation' => true, 'solution' => true, 'hint' => true, 'reasoning' => true, 'step' => true,
        'how to see what this asks' => false, 'revise in the chapter document' => false,
    ];

    private const DIFFICULTY = ['E' => 'easy', 'M' => 'medium', 'H' => 'hard'];

    public function __construct(private PdfTool $tool, private ImportValidator $validator) {}

    public function parse(string $path, ?callable $progress = null): ParseResult
    {
        $doc = $this->tool->extract($path);
        $lines = $this->bodyLines($doc);
        $result = new ParseResult(expected: DocumentFacts::expectedCount($lines, $doc["title"] ?? ""));

        [$blocks, $keyLines] = $this->splitIntoBlocks($lines);
        $result->answerKey = DocumentFacts::readKey($keyLines);

        $images = collect($doc['pages'])->mapWithKeys(fn ($p) => [$p['number'] => $p['images']])->all();
        foreach ($blocks as $block) {
            $draft = $this->parseBlock($block);
            $rebuilt = collect($block['lines'])->pluck('math')->flatten()->unique()->intersect(['frac', 'root']);
            if ($rebuilt->isNotEmpty()) {
                $draft['warnings'][] = ['level' => 'warning', 'message' => 'A '.$rebuilt->map(fn ($k) => $k === 'frac' ? 'fraction' : 'square root')->implode(' and ').' in this question was rebuilt from the page layout. Compare it with the PDF (or use “Parse PDF with AI”).'];
            }
            if ($this->touchesImage($block, $images)) {
                $draft['warnings'][] = ['level' => 'warning', 'message' => 'This question may contain a figure or diagram. Figures are not imported: add the picture in the editor if needed.'];
            }
            $result->questions[] = $draft;
        }

        if (! $result->questions) {
            $result->notes[] = 'No questions were recognised in this PDF. It may use a different layout: try “PDF with AI”.';
        }
        $result->notes = $this->validator->check($result);

        return $result;
    }

    // ------------------------------------------------------------------ 1. lines

    /**
     * All lines in reading order with running headers/footers removed
     * (text that repeats at the top or bottom of many pages, and "Page 3").
     *
     * @return array<int,array>
     */
    private function bodyLines(array $doc): array
    {
        $pages = $doc['pages'];
        $seen = [];
        foreach ($pages as $page) {
            foreach ($page['lines'] as $line) {
                if ($this->inMargin($line, $page)) {
                    $seen[$this->shape($line['text'])] = ($seen[$this->shape($line['text'])] ?? 0) + 1;
                }
            }
        }
        $repeat = max(2, (int) ceil(count($pages) * 0.3));

        $out = [];
        foreach ($pages as $page) {
            foreach ($page['lines'] as $line) {
                $text = trim($line['text']);
                // A question that starts at the very top of a page ("Question 6", "Q7 · …") is not a running header
                $startsQuestion = preg_match('/^(Question\s+\d+(?:$|[\s\x{a0}]*[·•⋅|]|[\s\x{a0}]+\S)|Q\s*\d+\s*[·•|.:)])/iu', $text);
                // Neither are its option and answer lines. "(a) 4 (b) 8" repeats on many pages, so it would otherwise
                // look like a running header when a question sits right at the top or bottom of a page.
                $belongsToQuestion = $startsQuestion || preg_match('/^(\(?[A-Da-d][).:]\s|Answer\s*[:\-]|Ans\s*[:\-])/u', $text);
                if (! $belongsToQuestion && $this->inMargin($line, $page) && (($seen[$this->shape($text)] ?? 0) >= $repeat || preg_match('/^Page\s+\d+(\s+of\s+\d+)?$/i', $text))) {
                    continue;
                }
                $out[] = $line + ['page' => $page['number']];
            }
        }

        return $out;
    }

    private function inMargin(array $line, array $page): bool
    {
        return $line['y0'] < $page['height'] * 0.08 || $line['y1'] > $page['height'] * 0.92;
    }

    /** "Page 12" and "Page 13" have the same shape. */
    private function shape(string $text): string
    {
        return preg_replace('/\d+/', '#', trim($text));
    }

    // ------------------------------------------------------------------ 2. blocks

    /** @return array{0: array<int,array>, 1: array<int,array>} question blocks and the answer-key lines */
    private function splitIntoBlocks(array $lines): array
    {
        $qStyle = count(array_filter($lines, fn ($l) => preg_match('/^Q\s*\d+\s*[\.\):]\s/u', $l['text']))) >= 3;
        // "Question 6" on a line of its own, then an id line such as "VEC-T1-C-006 ⋅ Topic 1 ⋅ Easy ⋅ Concept"
        $wordStyle = ! $qStyle && count(array_filter($lines, fn ($l) => preg_match('/^Question\s+\d+$/iu', trim($l['text'])))) >= 3;
        // "Question 1 · 1.1 The order of operations · Easy · Concept" with everything on one line
        $inlineStyle = ! $qStyle && ! $wordStyle && count(array_filter($lines, fn ($l) => preg_match('/^Question\s+\d+[\s\x{a0}]+\S/iu', trim($l['text'])))) >= 3;
        $blocks = [];
        $current = null;
        $part = null;
        $last = 0;
        $headerX = $inlineStyle ? min(array_map(fn ($l) => $l['x0'], array_filter($lines, fn ($l) => preg_match('/^Question\s+\d+[\s\x{a0}]+\S/iu', trim($l['text']))))) : 0;
        // In a paper with "Question 61 ..." headers, "1. text" lines are questions only when at least five of them sit at the
        // same margin as the headers; otherwise they are the numbered steps of the explanations.
        $numberedAtMargin = $inlineStyle && count(array_filter($lines, fn ($l) => preg_match('/^\d{1,3}\s*[\.\)]\s+\S/u', trim($l['text'])) && $l['x0'] <= $headerX + 3)) >= 5;
        $skipIntro = false;
        $stemX = null; // left edge of the question numbers in a "1. 2. 3." paper
        $keyLines = [];

        foreach ($lines as $i => $line) {
            $text = trim($line['text']);

            if (preg_match('/^answer\s*key\b/i', $text)) {
                $keyLines = array_slice($lines, $i + 1);
                break;
            }
            // "Part 4: Exam-level extension (Questions 56-100)" opens a page of introduction and a table: not part of any question.
            if ((preg_match('/^Part\s+[A-Za-z0-9]+\s*:.*\bQuestions?\s+\d+\s*[-–]\s*\d+/iu', $text) || preg_match('/^Added questions\b/iu', $text)) && ($line['size'] ?? 0) >= 11) {
                $skipIntro = true;
                continue;
            }
            if ($skipIntro) {
                if (preg_match('/^(Question\s+\d+|Q\s*\d+\s*[\.\):·])/iu', $text) || preg_match('/^\d{1,3}\s*[\.\)]\s+\S/u', $text)) {
                    $skipIntro = false;
                } else {
                    continue;
                }
            }

            if (preg_match('/^(Part\s+\d+)\s*[–\-—:]/u', $text, $m)) {
                $part = $m[1];
                continue;
            }
            if (preg_match('/^[A-Z]\.\s+[A-Za-z ]+\bquestions?$/u', $text)) {
                continue; // a heading such as "A. Concept questions"
            }
            // Big bold headings and page banners ("Part D: Additional exam-level questions", "Type 3: Simplify and compare")
            // sit between questions: they are not part of the explanation above them.
            if (preg_match('/^(Part\s+[A-Za-z0-9]+|Type\s+\d+|Section\s+[\d.]+)(\s*:|\s+\S)/u', $text) && ! empty($line['bold']) && ($line['size'] ?? 0) >= 11) {
                continue;
            }
            if (preg_match('/^\d+\.\d+\s+\S/u', $text) && ($line['size'] ?? 0) >= 12) {
                continue; // a numbered section title such as "1.1 What interest is, and the simple interest formula"
            }
            if (preg_match('/^(\S)\1(\S)\2(\S)\3(\S)\4/u', $text)) {
                continue; // a page header printed twice over, so every letter is doubled ("TTrriiaannggleess")
            }
            if ($wordStyle && preg_match('/^Question\s+(\d+)$/iu', $text, $m)) {
                $this->closeBlock($blocks, $current);
                $current = $this->newBlock((int) $m[1], $part, $line, []);
                $current['stemSeen'] = true;
                $current['expectMeta'] = true;
                $last = (int) $m[1];
                continue;
            }
            if ($inlineStyle && preg_match('/^Question\s+(\d+)[\s\x{a0}]+(\S.*)$/iu', $text, $m)) {
                $this->closeBlock($blocks, $current);
                $current = $this->newBlock((int) $m[1], $part, $line, $this->meta($m[2]));
                $current['stemSeen'] = true;
                $last = (int) $m[1];
                continue;
            }
            if ($wordStyle && $current && $current['expectMeta']) {
                $current['expectMeta'] = false;
                if (preg_match('/^[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)+\s*[⋅·•]\s*\S/u', $text)) {
                    $meta = $this->meta($text);
                    $current['part'] = $meta['part'] ?? $current['part'];
                    $current['level'] = $meta['level'] ?? null;
                    $current['kind'] = $meta['kind'] ?? null;
                    continue;
                }
            }

            if ($qStyle && preg_match('/^Q\s*(\d+)\s*[·•|]\s*(.+)$/u', $text, $m)) {
                $this->closeBlock($blocks, $current);
                $current = $this->newBlock((int) $m[1], $part, $line, $this->meta($m[2]));
                $last = (int) $m[1];
                continue;
            }

            $stem = $qStyle
                ? (preg_match('/^Q\s*(\d+)\s*[\.\):]\s*(.*)$/u', $text, $m) ? [(int) $m[1], $m[2]] : null)
                : (preg_match($inlineStyle ? '/^(\d{1,3})\s*\.\s+(\S.*)$/u' : '/^(\d{1,3})\s*[\.\)]\s+(\S.*)$/u', $text, $m) && (int) $m[1] === $last + 1 // a wrapped line "19) ÷ 2" is not question 19
                    // the numbered steps of an explanation ("1. 64 = 2^6") are indented: a real question number sits at the margin
                    // (in a paper that also has "Question 61 ..." headers, the margin of those headers is the reference)
                    && ($inlineStyle ? ($numberedAtMargin && $line['x0'] <= $headerX + 3 && $this->optionsFollow($lines, $i)) : ($stemX === null || $line['x0'] <= $stemX + 4)) ? [(int) $m[1], $m[2]] : null);

            if ($stem) {
                if ($current && $current['number'] === $stem[0] && ! $current['stemSeen']) {
                    $current['stemSeen'] = true; // the header line came first, this is its question text
                } else {
                    $this->closeBlock($blocks, $current);
                    $current = $this->newBlock($stem[0], $part, $line, []);
                    $current['stemSeen'] = true;
                }
                $current['lines'][] = ['text' => $stem[1], 'rich' => $stem[1], 'segments' => [['text' => $stem[1], 'rich' => $stem[1], 'x0' => $line['x0']]], 'math' => $line['math'] ?? [], 'page' => $line['page'], 'y1' => $line['y1']];
                $last = $stem[0];
                $stemX ??= $line['x0'];
                continue;
            }

            if ($current) {
                $current['lines'][] = ['text' => $text, 'rich' => $line['rich'] ?? $text, 'segments' => $line['segments'], 'math' => $line['math'] ?? [], 'page' => $line['page'], 'y1' => $line['y1']];
            }
        }
        $this->closeBlock($blocks, $current);

        return [$blocks, $keyLines];
    }

    /** True when option "(a)" comes within the next few lines before any explanation label: a question stem, not a numbered step. */
    private function optionsFollow(array $lines, int $i): bool
    {
        for ($k = $i + 1; $k <= $i + 6 && isset($lines[$k]); $k++) {
            $t = trim($lines[$k]['text']);
            if (preg_match('/^\(?[aA][\)\.]\s/u', $t)) {
                return true;
            }
            if (preg_match('/^(what the question is asking|step by step|explanation|why the other|concept behind|answer)/iu', $t) || preg_match('/^Question\s+\d+/iu', $t)) {
                return false;
            }
        }

        return false;
    }

    private function newBlock(int $number, ?string $part, array $line, array $meta): array
    {
        return [
            'number' => $number, 'part' => $meta['part'] ?? $part, 'level' => $meta['level'] ?? null, 'kind' => $meta['kind'] ?? null,
            'startPage' => $line['page'], 'startY' => $line['y0'], 'lines' => [], 'stemSeen' => false, 'expectMeta' => false,
        ];
    }

    private function closeBlock(array &$blocks, ?array &$current): void
    {
        if ($current && $current['lines']) {
            $blocks[] = $current;
        }
        $current = null;
    }

    /** "Numerical" is the same kind of question as "Calculation". */
    private function kindName(string $word): string
    {
        $word = strtolower($word);

        return $word === 'numerical' ? 'calculation' : $word;
    }

    /** "Part 3 · Calculation · Level E · MCQ" */
    private function meta(string $text): array
    {
        $meta = [];
        foreach (preg_split('/\s*[·•|⋅]\s*/u', $text) as $piece) {
            if (preg_match('/^(Part|Topic|Section|Chapter)\s*(\d+)$/i', $piece, $m)) {
                $meta['part'] = ucfirst(strtolower($m[1])).' '.$m[2];
            } elseif (preg_match('/^(easy|medium|hard)$/i', $piece, $m)) {
                $meta['level'] = strtoupper($m[1][0]);
            } elseif (preg_match('/\s(easy|medium|hard)$/i', $piece, $m)) {
                $meta['level'] = strtoupper($m[1][0]); // "1.1 The order of operations: BODMAS Easy"
            } elseif (preg_match('/^Level\s*([EMH])\b/i', $piece, $m)) {
                $meta['level'] = strtoupper($m[1]);
            } elseif (preg_match('/^(concept|calculation|application|recall|reasoning|numerical)/i', $piece, $m)) {
                $meta['kind'] = $this->kindName($m[1]);
            } elseif (preg_match('/\s([EMH])$/', $piece, $m)) {
                $meta['level'] = $m[1]; // "1.1 What interest is, and the formula E": the level letter ends the section title
            } elseif (preg_match('/^([CN])$/', $piece, $m)) {
                $meta['kind'] = $m[1] === 'C' ? 'concept' : 'calculation'; // "· C" concept, "· N" numerical
            }
        }

        return $meta;
    }

    // ------------------------------------------------------------------ 3. one question

    private function parseBlock(array $block): array
    {
        $draft = ParseResult::blank();
        $stem = [];
        $options = [];       // letter => text pieces
        $columns = [];       // letter => x of its column
        $started = [];       // letters in the order they appeared
        $sections = [];      // [label, pieces]
        $mode = 'stem';
        $answerLetter = null;
        $answerText = '';

        foreach ($block['lines'] as $line) {
            $text = $line['text'];              // clean text: used to recognise the structure
            $rich = $line['rich'] ?? $text;     // same text with printed vectors marked: used for the content

            // Answer line ends the options
            if ($mode !== 'explain' && preg_match('/^(?:correct\s+)?(?:answer|ans)\.?\s*[:\-–]\s*\(?([a-dA-D])\)?\s*(.*)$/iu', $text, $m)) {
                $answerLetter = strtolower($m[1]);
                $answerText = trim($m[2]);
                // "Answer: (a) integers (Easy · Concept)": the tag in brackets is the difficulty and kind, not part of the answer
                if (preg_match('/\s*\((easy|medium|hard)\s*[·•|⋅]\s*([A-Za-z]+)\)\s*$/iu', $answerText, $tag)) {
                    $answerText = trim(substr($answerText, 0, -strlen($tag[0])));
                    $block['level'] ??= strtoupper($tag[1][0]);
                    $block['kind'] ??= $this->kindName($tag[2]);
                }
                $mode = 'explain';
                continue;
            }

            if ($mode === 'explain') {
                $label = $this->label($rich);
                if ($label) {
                    $sections[] = [$label[0], [$label[1]]];
                } elseif ($sections) {
                    $sections[array_key_last($sections)][1][] = $rich;
                }
                continue;
            }

            $startsOption = collect($line['segments'])->contains(fn ($s) => $this->optionMarker($s['text']));
            if ($mode === 'stem' && ! $startsOption) {
                $stem[] = $rich;
                continue;
            }
            $mode = 'options';

            foreach ($line['segments'] as $segment) {
                // a segment may hold several options: "(a) 27 (b) 81"
                foreach (preg_split('/(?<=\s)(?=\([a-dA-D]\)\s)/u', $segment['rich'] ?? $segment['text']) as $n => $piece) {
                    if ($marker = $this->optionMarker($piece)) {
                        [$letter, $rest] = $marker;
                        $options[$letter] = [$rest];
                        $columns[$letter] = $n === 0 ? $segment['x0'] : ($columns[$letter] ?? $segment['x0']);
                        $started = [...array_diff($started, [$letter]), $letter];
                    } elseif ($started) {
                        $options[$this->owner($segment['x0'], $columns, $started)][] = trim($piece);
                    }
                }
            }
        }

        $draft['number'] = $block['number'];
        $draft['part'] = $block['part'];
        $draft['kind'] = $block['kind'];
        $draft['difficulty'] = self::DIFFICULTY[$block['level'] ?? ''] ?? null;
        $draft['page'] = $block['startPage'];
        $draft['body'] = '<p>'.$this->html(implode(' ', $stem)).'</p>';
        foreach (['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4] as $letter => $n) {
            $draft["option_{$n}"] = isset($options[$letter]) ? $this->html(implode(' ', $options[$letter])) : null;
        }
        $draft['correct'] = $answerLetter ? ord($answerLetter) - 96 : null;
        $draft['_answer_text'] = $answerText !== '' ? $answerText : null;
        $draft['explanation'] = $this->explanation($sections);

        return $draft;
    }

    /** ["a", "text"] when the text starts with "(a)" or "a)". */
    private function optionMarker(string $text): ?array
    {
        return preg_match('/^(?:\(([a-dA-D])\)|([a-dA-D])\))\s*(.*)$/su', trim($text), $m)
            ? [strtolower($m[1] ?: $m[2]), trim($m[3])]
            : null;
    }

    /**
     * Which option does this continuation text belong to? The option whose column is the nearest one
     * to the left of the text (two-column grids), and among options in the same column the latest one.
     */
    private function owner(float $x, array $columns, array $started): string
    {
        $left = array_filter($columns, fn ($c) => $c <= $x + 3);
        if (! $left) {
            return end($started);
        }
        $nearest = max($left);
        $candidates = array_keys(array_filter($left, fn ($c) => abs($c - $nearest) < 3));
        $latest = array_values(array_intersect(array_reverse($started), $candidates));

        return $latest[0];
    }

    /** @return array{0:string,1:string}|null [label as written, rest of the line] */
    private function label(string $text): ?array
    {
        if (preg_match('/^([A-Za-z][A-Za-z\' ]{1,40}?)\s*(?:[:.]\s*|$)(.*)$/su', $text, $m) && array_key_exists(mb_strtolower(trim($m[1])), self::LABELS)) {
            return [trim($m[1]), trim($m[2])];
        }

        return null;
    }

    /** HTML for the parts a student should see after the test. */
    private function explanation(array $sections): ?string
    {
        $html = [];
        foreach ($sections as [$label, $pieces]) {
            if (! (self::LABELS[mb_strtolower($label)] ?? false)) {
                continue;
            }
            // One line per numbered step or bullet; a line that only continues the one above (a wrapped line) joins it.
            $lines = [];
            foreach ($pieces as $piece) {
                $piece = trim(preg_replace('/\s*✓/u', '', $piece));
                if ($piece === '') {
                    continue;
                }
                if (! $lines || preg_match('/^(\d+[\.\)]|[•▪■◆●-]|\([a-dA-D]\))\s/u', $piece)) {
                    $lines[] = $piece;
                } else {
                    $lines[count($lines) - 1] .= ' '.$piece;
                }
            }
            if ($lines) {
                $html[] = '<p><strong>'.$this->html($label).':</strong>'.(count($lines) > 1 ? '<br>' : ' ').implode('<br>', array_map(fn ($line) => $this->html($line), $lines)).'</p>';
            }
        }

        return $html ? implode('', $html) : null;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Escapes text for HTML. The tool's ^{…} / _{…} markers become superscripts / subscripts, and a rebuilt fraction or
     * root (\x03 LaTeX \x05 plain \x04) becomes real maths: $\frac{x+5}{2}$ is shown stacked by the admin list and the
     * student app, and stays editable as text.
     */
    private function html(string $text): string
    {
        $text = htmlspecialchars(preg_replace('/\s+/u', ' ', trim($text)), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace('/\x01([^\x02]*)\x02/u', '<strong>$1</strong>', $text); // a single bold letter is a printed vector: A, î
        $text = str_replace(["\x01", "\x02"], '', $text);

        $out = '';
        foreach (preg_split('/(\x03[^\x04]*\x04)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            if (str_starts_with($part, "\x03")) {
                $out .= '$'.explode("\x05", substr($part, 1, -1))[0].'$';   // inside maths the ^{…} stays LaTeX
            } else {
                $out .= preg_replace(['/\^\{([^}]*)\}/u', '/_\{([^}]*)\}/u'], ['<sup>$1</sup>', '<sub>$1</sub>'], $part);
            }
        }

        return $out;
    }

    /** Does any picture on the question's pages sit between its first and last line? */
    private function touchesImage(array $block, array $images): bool
    {
        $last = end($block['lines']);
        foreach (range($block['startPage'], $last['page']) as $page) {
            $from = $page === $block['startPage'] ? $block['startY'] : 0;
            $to = $page === $last['page'] ? $last['y1'] : INF;
            foreach ($images[$page] ?? [] as [, $y0, , $y1]) {
                if ($y1 >= $from && $y0 <= $to) {
                    return true;
                }
            }
        }

        return false;
    }
}
