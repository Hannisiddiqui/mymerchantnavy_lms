<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Enums\ImportMethod;
use App\Filament\Resources\CourseResource;
use App\Filament\Resources\TestResource;
use App\Filament\Support\ImportActions;
use App\Filament\Support\QuestionForm;
use App\Models\Question;
use App\Models\Subject;
use App\Services\Tests\QuestionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * THE TEST QUESTION BUILDER.
 *   Left:  the questions in this test (drag to reorder; edit / replace / remove).
 *   Right: the question bank with search and filters (add one, or tick several and add together).
 * Layout: resources/views/filament/test-builder/page.blade.php. Saving a question: App\Services\Tests\QuestionService.
 */
class ManageQuestions extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TestResource::class;

    protected string $view = 'filament.test-builder.page';

    /** Most questions one test can hold. */
    public const MAX_QUESTIONS = 200;

    // Question bank filters
    public string $search = '';

    public string $difficulty = '';

    public string $subject = '';

    public bool $hideAdded = true;

    public int $limit = 15;

    /** Ticked bank questions, waiting for "Add selected". */
    public array $selected = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    public function getBreadcrumb(): string
    {
        return 'Questions';
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'difficulty', 'subject', 'hideAdded'], true)) {
            $this->limit = 15;
            $this->selected = [];
        }
    }

    protected function getViewData(): array
    {
        $test = $this->getRecord();
        $inTest = $test->questions()->with('options')->withCount('tests')->get();
        $inTestIds = $inTest->pluck('id');

        $bank = Question::query()->with('options')->withCount('tests')
            ->when($this->hideAdded, fn (Builder $q) => $q->whereNotIn('id', $inTestIds))
            ->when($this->difficulty, fn (Builder $q) => $q->where('difficulty', $this->difficulty))
            ->when($this->subject, fn (Builder $q) => $q->where('subject_id', $this->subject))
            ->when(trim($this->search) !== '', function (Builder $q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn (Builder $w) => $w->whereLike('body', $term)
                    ->orWhereLike('explanation', $term)
                    ->orWhereHas('options', fn (Builder $o) => $o->whereLike('body', $term))
                    ->orWhere('id', ctype_digit(trim($this->search)) ? (int) trim($this->search) : 0));
            })
            ->latest('id');

        $bankTotal = (clone $bank)->count();

        return [
            'test' => $test,
            'inTest' => $inTest,
            'inTestIds' => $inTestIds->all(),
            'bank' => $bank->limit($this->limit)->get(),
            'bankTotal' => $bankTotal,
            'subjects' => Subject::with('course')->get()->mapWithKeys(fn ($s) => [$s->id => "{$s->course->title} — {$s->title}"]),
            'service' => app(QuestionService::class),
            'max' => self::MAX_QUESTIONS,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newQuestion')->label('Write a new question')->icon('heroicon-m-plus')
                ->slideOver()->modalWidth('4xl')->modalHeading('New question')->modalSubmitActionLabel('Save and add to test')
                ->schema(QuestionForm::schema())
                ->action(function (array $data) {
                    $question = app(QuestionService::class)->save($data);
                    $this->attach([$question->id]);
                }),
            // PDF / Excel → a review list first; the approved questions come back to THIS test
            ImportActions::import($this->getRecord()),
            ImportActions::import($this->getRecord(), ImportMethod::PdfAi),
            ImportActions::template(),
            Action::make('settings')->label('Test settings')->icon('heroicon-m-cog-6-tooth')->color('gray')
                ->url(fn () => TestResource::getUrl('edit', ['record' => $this->getRecord()])),
            // When the questions are done the admin goes straight back to the course outline.
            Action::make('done')->label('Done — back to course')->icon('heroicon-m-check-circle')->color('success')
                ->url(fn () => $this->courseUrl()),
        ];
    }

    private function courseUrl(): string
    {
        return CourseResource::getUrl('curriculum', ['record' => $this->getRecord()->course_id]);
    }

    /** Courses › this course › this test, so the way back is always one click. */
    public function getBreadcrumbs(): array
    {
        $test = $this->getRecord();

        return [
            CourseResource::getUrl('index') => 'Courses',
            $this->courseUrl() => $test->course->title,
            '#' => $test->title,
        ];
    }

    /** Edit a question (the change applies wherever it is used). arguments: id */
    public function editQuestionAction(): Action
    {
        return Action::make('editQuestion')->slideOver()->modalWidth('4xl')->modalHeading('Edit question')->modalSubmitActionLabel('Save changes')
            ->modalDescription(function (array $arguments): ?string {
                $uses = Question::withCount('tests')->find($arguments['id'])?->tests_count ?? 0;

                return $uses > 1 ? "This question is used in {$uses} tests. Your changes apply to all of them." : null;
            })
            ->schema(QuestionForm::schema())
            ->fillForm(fn (array $arguments) => app(QuestionService::class)->toForm(Question::with('options')->findOrFail($arguments['id'])))
            ->action(function (array $data, array $arguments) {
                app(QuestionService::class)->save($data, Question::findOrFail($arguments['id']));
                Notification::make()->title('Question saved')->success()->send();
            });
    }

    /** The eye button: the whole question as students see it, with the correct answer and the explanation. arguments: id */
    public function previewQuestionAction(): Action
    {
        return Action::make('previewQuestion')->slideOver()->modalWidth('3xl')
            ->modalHeading(fn (array $arguments) => 'Preview: question #'.($arguments['id'] ?? ''))
            ->modalSubmitAction(false)->modalCancelActionLabel('Close')
            ->modalContent(function (array $arguments) {
                $question = Question::with('options')->findOrFail($arguments['id']);
                $options = $question->options->values();

                return view('filament.question-preview', [
                    'body' => $question->body,
                    'options' => $options->mapWithKeys(fn ($option, $i) => [$i + 1 => $option->body])->all(),
                    'correct' => 1 + (int) $options->search(fn ($option) => $option->is_correct),
                    'explanation' => $question->explanation,
                ]);
            });
    }

    /** Swap a question for another one, keeping its place in the test. arguments: id */
    public function replaceQuestionAction(): Action
    {
        return Action::make('replaceQuestion')->modalHeading('Replace question')->modalWidth('2xl')->modalSubmitActionLabel('Replace')
            ->modalDescription('The new question takes the same position. The old one stays in the question bank.')
            ->schema([
                Select::make('replacement')->label('Replace with')->required()->searchable()->allowHtml()
                    ->getSearchResultsUsing(fn (string $search) => $this->searchBank($search))
                    ->getOptionLabelUsing(fn ($value) => e(Str::limit(strip_tags(Question::find($value)?->body ?? ''), 90)))
                    ->helperText('Search the question bank by words in the question or its options.')
                    ->createOptionModalHeading('New question')
                    ->createOptionForm(QuestionForm::schema())
                    ->createOptionUsing(fn (array $data) => app(QuestionService::class)->save($data)->getKey()),
            ])
            ->action(function (array $data, array $arguments) {
                $test = $this->getRecord();
                DB::transaction(function () use ($test, $data, $arguments) {
                    $position = DB::table('test_questions')->where('test_id', $test->id)->where('question_id', $arguments['id'])->value('position');
                    $test->questions()->detach($arguments['id']);
                    if (! $test->questions()->whereKey($data['replacement'])->exists()) {
                        $test->questions()->attach($data['replacement'], ['position' => $position ?? 0]);
                    }
                });
                Notification::make()->title('Question replaced')->success()->send();
            });
    }

    // ---- inline actions ----

    public function addQuestion(int $id): void
    {
        $this->attach([$id]);
    }

    public function addSelected(): void
    {
        $this->attach(array_map('intval', $this->selected));
        $this->selected = [];
    }

    public function removeQuestion(int $id): void
    {
        $this->getRecord()->questions()->detach($id);
        $this->normalizePositions();
    }

    /** @param  array<int,string>  $ids  "q-12" style ids in the new order */
    public function reorder(array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            DB::table('test_questions')->where('test_id', $this->getRecord()->id)
                ->where('question_id', (int) str_replace('q-', '', $id))->update(['position' => $position]);
        }
    }

    public function loadMore(): void
    {
        $this->limit += 15;
    }

    // ----

    /** Adds questions at the end, skipping ones already in the test and respecting the limit. */
    private function attach(array $ids): void
    {
        $test = $this->getRecord();
        $current = $test->questions()->pluck('questions.id');
        $new = collect($ids)->diff($current)->values();
        $room = self::MAX_QUESTIONS - $current->count();

        if ($new->count() > $room) {
            Notification::make()->title('A test can hold at most '.self::MAX_QUESTIONS.' questions')->warning()->send();
            $new = $new->take(max($room, 0));
        }
        if ($new->isEmpty()) {
            return;
        }

        $position = (DB::table('test_questions')->where('test_id', $test->id)->max('position') ?? -1) + 1;
        $test->questions()->attach($new->mapWithKeys(fn ($id, $i) => [$id => ['position' => $position + $i]])->all());

        Notification::make()->title($new->count() === 1 ? 'Question added to the test' : $new->count().' questions added to the test')->success()->send();
    }

    private function normalizePositions(): void
    {
        $this->getRecord()->questions()->pluck('questions.id')->each(fn ($id, $i) => DB::table('test_questions')
            ->where('test_id', $this->getRecord()->id)->where('question_id', $id)->update(['position' => $i]));
    }

    /** @return array<int,string> id => label, for search selects */
    private function searchBank(string $search): array
    {
        $inTest = $this->getRecord()->questions()->pluck('questions.id');
        $term = '%'.$search.'%';

        return Question::query()->whereNotIn('id', $inTest)
            ->where(fn ($q) => $q->whereLike('body', $term)->orWhereHas('options', fn ($o) => $o->whereLike('body', $term)))
            ->latest('id')->limit(30)->get()
            ->mapWithKeys(fn (Question $q) => [$q->id => e('#'.$q->id.' · '.Str::limit(strip_tags($q->body), 90))])->all();
    }
}
