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
        // Questions 1-2: "1. text" with numbered explanation steps ("1.", "2.", "3."); questions 3-5: "Question 3 Medium · Numerical".
        $result = app(PdfRuleParser::class)->parse(base_path('tests/Fixtures/mixed-layout.pdf'));
        $questions = collect($result->questions)->keyBy('number');

        $this->assertSame([1, 2, 3, 4, 5], $questions->keys()->all());
        $this->assertSame([1, 3, 3, 3, 2], $questions->pluck('correct')->all());
        $this->assertSame('integers', $questions[1]['option_1']);
        $this->assertSame('easy', $questions[1]['difficulty']);   // from "(Easy · Concept)" on the answer line
        $this->assertSame('concept', $questions[1]['kind']);
        $this->assertSame('medium', $questions[3]['difficulty']); // from the "Question 3 Medium · Numerical" header
        $this->assertSame('calculation', $questions[3]['kind']);  // "Numerical" means a calculation question
        // every step and bullet on its own line, and a banner between questions is not glued to the explanation above it
        $this->assertStringContainsString('<strong>Explanation:</strong><br>1. -15 is negative.<br>2. It is an integer.', $questions[1]['explanation']);
        $this->assertStringNotContainsString('Part D', $questions[2]['explanation']);
        foreach ($questions as $number => $q) {
            $this->assertSame([], $q['warnings'], "Q{$number}");
        }
    }
}
