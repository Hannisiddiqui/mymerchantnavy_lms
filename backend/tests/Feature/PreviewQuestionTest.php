<?php

namespace Tests\Feature;

use App\Enums\TestKind;
use App\Filament\Resources\TestResource\Pages\ManageQuestions;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Test;
use App\Services\Tests\QuestionService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PreviewQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_eye_button_shows_the_question_the_correct_answer_and_the_explanation(): void
    {
        $this->actingAs(Admin::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password']), 'admin');
        Filament::setCurrentPanel('admin');
        $test = Test::create(['course_id' => Course::create(['title' => 'C', 'slug' => 'c'])->id, 'title' => 'T', 'kind' => TestKind::Practice, 'duration_minutes' => 10]);
        $question = app(QuestionService::class)->save([
            'body' => '<p>What is the square root of 81?</p>', 'explanation' => '<p><strong>Explanation:</strong><br>1. 9 x 9 = 81.</p>',
            'correct' => 3, 'option_1' => '7', 'option_2' => '8', 'option_3' => '9', 'option_4' => '10',
        ]);
        $test->questions()->attach($question->id, ['position' => 0]);

        $page = Livewire::test(ManageQuestions::class, ['record' => $test->id])
            ->assertSeeHtml('previewQuestion') // the eye button is on the page
            ->mountAction(TestAction::make('previewQuestion')->arguments(['id' => $question->id]));

        // what the pop-up shows
        $html = (string) $page->instance()->getMountedAction()->getModalContent();

        $this->assertStringContainsString('What is the square root of 81?', $html);
        $this->assertStringContainsString('1. 9 x 9 = 81.', $html);
        $this->assertStringContainsString('Solution', $html);
        $this->assertSame(1, substr_count($html, '✓ correct'), 'exactly one option is marked correct');
        $this->assertMatchesRegularExpression('/>\s*C\s*<\/b>.{0,400}✓ correct/s', $html, 'the marked option is C (9)');
    }
}
