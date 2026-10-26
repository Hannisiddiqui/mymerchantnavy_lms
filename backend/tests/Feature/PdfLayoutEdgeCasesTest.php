<?php

namespace Tests\Feature;

use App\Services\Imports\PdfRuleParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Page layouts that once broke the automatic PDF reader (fixture built by tests/Fixtures/make_layout_edge_cases.py).
 */
class PdfLayoutEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private function questions(): array
    {
        $result = app(PdfRuleParser::class)->parse(base_path('tests/Fixtures/layout-edge-cases.pdf'));

        return collect($result->questions)->keyBy('number')->all();
    }

    public function test_a_question_at_the_very_top_of_a_page_keeps_all_its_options(): void
    {
        // "(a) 4 (b) 8" has the same shape on every page, so it used to be dropped as a running header.
        $questions = $this->questions();

        foreach ([1, 2, 3, 4] as $n) {
            $this->assertSame(['4', '8', '7', '9'], [$questions[$n]['option_1'], $questions[$n]['option_2'], $questions[$n]['option_3'], $questions[$n]['option_4']], "Q{$n}");
            $this->assertSame(4, $questions[$n]['correct'], "Q{$n}");
        }
    }

    public function test_stacked_fractions_in_a_two_column_option_grid_stay_in_their_own_option(): void
    {
        $q = $this->questions()[5];

        // Each option is exactly its own fraction: nothing borrowed from the row below, and the fraction next to
        // "(b)" does not end up in the question text.
        // and they are stored as LaTeX, so they are drawn as real stacked fractions.
        $this->assertSame(['$\frac{x}{2}$ + 5', '$\frac{1}{2x+5}$', '$\frac{x+5}{2}$', '$\frac{x+3}{2}$'], [$q['option_1'], $q['option_2'], $q['option_3'], $q['option_4']]);
        $this->assertStringNotContainsString('2x+5', $q['body']);
        $this->assertSame(4, $q['correct']);
    }

    public function test_question_header_on_one_line_with_numbered_steps_reads_each_question_once(): void
    {
        // "Question 1 <section> Easy · Concept" on one line; "1. Write the sum." inside the explanation is not a question.
        $result = app(PdfRuleParser::class)->parse(base_path('tests/Fixtures/inline-header.pdf'));
        $questions = collect($result->questions)->keyBy('number');

        $this->assertSame([1, 2, 3], $questions->keys()->all());
        $this->assertSame(3, $questions[1]['correct']);
        $this->assertSame('easy', $questions[1]['difficulty']);
        $this->assertSame('concept', $questions[1]['kind']);
        $this->assertSame(['1', '2', '3', '9'], [$questions[1]['option_1'], $questions[1]['option_2'], $questions[1]['option_3'], $questions[1]['option_4']]);
        $this->assertStringContainsString('Step by step', $questions[1]['explanation']);
        $this->assertStringContainsString('2. Add them.', $questions[1]['explanation']);
    }

    public function test_a_paper_with_numbered_questions_and_question_headers_is_read_completely(): void
    {
        // Questions 1-5: "1. text" with numbered explanation steps ("1.", "2.", "3."); questions 6-8: "Question 6 Medium · Numerical".
        $result = app(PdfRuleParser::class)->parse(base_path('tests/Fixtures/mixed-layout.pdf'));
        $questions = collect($result->questions)->keyBy('number');

        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $questions->keys()->all());
        $this->assertSame([1, 3, 2, 2, 2, 3, 3, 2], $questions->pluck('correct')->all());
        $this->assertSame('integers', $questions[1]['option_1']);
        $this->assertSame('easy', $questions[1]['difficulty']);   // from "(Easy · Concept)" on the answer line
        $this->assertSame('concept', $questions[1]['kind']);
        $this->assertSame('medium', $questions[6]['difficulty']); // from the "Question 6 Medium · Numerical" header
        $this->assertSame('calculation', $questions[6]['kind']);  // "Numerical" means a calculation question
        // every step and bullet on its own line, and a banner between questions is not glued to the explanation above it
        $this->assertStringContainsString('<strong>Explanation:</strong><br>1. -15 is negative.<br>2. It is an integer.', $questions[1]['explanation']);
        $this->assertStringNotContainsString('Part D', $questions[2]['explanation']);
        foreach ($questions as $number => $q) {
            $this->assertSame([], $q['warnings'], "Q{$number}");
        }
    }

    public function test_level_letters_an_introduction_page_and_repeated_headings_stay_out_of_the_questions(): void
    {
        // "... E · N", a "Part 2: Exam-level extension (Questions 2-3)" page with a table, a plain section title and a header with doubled letters.
        $result = app(PdfRuleParser::class)->parse(base_path('tests/Fixtures/extension-layout.pdf'));
        $questions = collect($result->questions)->keyBy('number');

        $this->assertSame([1, 2, 3], $questions->keys()->all());
        $this->assertSame([2, 2, 2], $questions->pluck('correct')->all());
        $this->assertSame(['easy', 'medium', 'medium'], $questions->pluck('difficulty')->all());
        $this->assertSame(['calculation', 'concept', 'concept'], $questions->pluck('kind')->all());
        foreach ($questions as $number => $q) {
            $plain = strip_tags($q['explanation']);
            foreach (['Section Added', 'extension', 'Total', 'Adding', 'SSuummss'] as $junk) {
                $this->assertStringNotContainsString($junk, $plain, "Q{$number} explanation must not contain: {$junk}");
            }
        }
        $this->assertStringContainsString('Step by step:</strong><br>1. Add 2 and 3.<br>2. The sum is 5.', $questions[1]['explanation']);
    }

    public function test_powers_in_two_column_options_are_kept_and_a_wrapped_line_is_not_the_next_question(): void
    {
        // Powers at about 83% of the text size ("x³ = 8x", "(x - 2)²") and a wrapped line "2) ÷ 2 (Section 1.1)." inside an explanation.
        $result = app(PdfRuleParser::class)->parse(base_path('tests/Fixtures/exponent-options.pdf'));
        $questions = collect($result->questions)->keyBy('number');

        $this->assertSame([1, 2, 3], $questions->keys()->all());
        $this->assertSame(['x² - 4 = 12', 'x + y = 9', 'x³ = 8x', '7x - 4 = 3x + 8'], [$questions[1]['option_1'], $questions[1]['option_2'], $questions[1]['option_3'], $questions[1]['option_4']]);
        $this->assertSame('x² = 9', $questions[2]['option_3']);
        $this->assertSame(['(x - 2)²', '(x + 2)(x - 2)', '(x + 2)²', 'x(x - 4)'], [$questions[3]['option_1'], $questions[3]['option_2'], $questions[3]['option_3'], $questions[3]['option_4']]);
        $this->assertStringContainsString('2) ÷ 2 (Section 1.1).', strip_tags($questions[1]['explanation']));
        $this->assertSame([4, 2, 2], $questions->pluck('correct')->all());
    }
}
