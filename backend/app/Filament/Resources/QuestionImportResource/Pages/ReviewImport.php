<?php

namespace App\Filament\Resources\QuestionImportResource\Pages;

use App\Enums\ImportMethod;
use App\Enums\TestKind;
use App\Filament\Resources\QuestionImportResource;
use App\Filament\Resources\TestResource;
use App\Filament\Support\QuestionForm;
use App\Jobs\ProcessQuestionImport;
use App\Models\ImportQuestion;
use App\Models\Test;
use App\Services\Imports\ImportValidator;
use App\Services\Imports\QuestionImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * REVIEW SCREEN for a question import.
 * The file has been read into draft questions. Here the admin looks at them (warnings are flagged),
 * fixes or skips any, and then sends the approved ones to a test. Nothing is added before that.
 * Layout: resources/views/filament/import-review/page.blade.php. Saving: App\Services\Imports\QuestionImportService.
 */
class ReviewImport extends Page
{
    use InteractsWithRecord;

    protected static string $resource = QuestionImportResource::class;

    protected string $view = 'filament.import-review.page';

    // Filters
    public string $show = 'all';   // all | pending | ok | attention | added | skipped

    public string $part = '';

    public string $search = '';

    public int $limit = 30;

    /** Ticked drafts (ids). */
    public array $selected = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Review: '.$this->getRecord()->original_name;
    }

    public function getBreadcrumb(): string
    {
        return 'Review';
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['show', 'part', 'search'], true)) {
            $this->limit = 30;
        }
    }

    protected function getViewData(): array
    {
        $import = $this->getRecord()->refresh();
        $all = $import->drafts()->get();
        $filtered = $this->filter($all);

        return [
            'import' => $import,
            'drafts' => $filtered->take($this->limit),
            'filteredCount' => $filtered->count(),
            'counts' => [
                'total' => $all->count(),
                'ok' => $all->where('status', 'pending')->filter(fn ($d) => $d->severity() === 'ok')->count(),
                'warning' => $all->where('status', 'pending')->filter(fn ($d) => $d->severity() === 'warning')->count(),
                'error' => $all->where('status', 'pending')->filter(fn ($d) => $d->severity() === 'error')->count(),
                'added' => $all->where('status', 'added')->count(),
                'skipped' => $all->where('status', 'skipped')->count(),
            ],
            'parts' => $all->pluck('part')->filter()->unique()->values(),
            'summary' => $import->summary ?? [],
        ];
    }

    private function filter(Collection $drafts): Collection
    {
        return $drafts
            ->when($this->part !== '', fn ($c) => $c->where('part', $this->part))
            ->when(trim($this->search) !== '', fn ($c) => $c->filter(fn ($d) => Str::contains(mb_strtolower(strip_tags($d->body)), mb_strtolower(trim($this->search)))
                || (string) $d->number === trim($this->search, ' Qq')))
            ->filter(fn (ImportQuestion $d) => match ($this->show) {
                'pending' => $d->status === 'pending',
                'ok' => $d->status === 'pending' && $d->severity() === 'ok',
                'attention' => $d->status === 'pending' && $d->severity() !== 'ok',
                'added' => $d->status === 'added',
                'skipped' => $d->status === 'skipped',
                default => true,
            })->values();
    }

    // ------------------------------------------------------------------ header buttons

    protected function getHeaderActions(): array
    {
        return [
            $this->addToTestAction(),
            Action::make('retry')->label('Read again')->icon('heroicon-m-arrow-path')->color('gray')
                ->visible(fn () => in_array($this->getRecord()->status, ['failed', 'parsing'], true))
                ->requiresConfirmation()->modalDescription('Reads the file again from the start.')
                ->action(function () {
                    $import = $this->getRecord();
                    $import->method === ImportMethod::PdfAi ? ProcessQuestionImport::dispatch($import->id) : app(QuestionImportService::class)->process($import);
                }),
            Action::make('discard')->label('Discard import')->icon('heroicon-m-trash')->color('danger')
                ->requiresConfirmation()->modalDescription('The draft list is deleted. Questions already added to tests are kept.')
                ->action(function () {
                    Storage::disk('local')->delete($this->getRecord()->file_path);
                    $this->getRecord()->delete();
                    $this->redirect(QuestionImportResource::getUrl('index'));
                }),
        ];
    }

    /** Sends the ticked drafts to a test (or creates a new test for them). */
    private function addToTestAction(): Action
    {
        return Action::make('addToTest')
            ->label(fn () => count($this->selected) ? 'Add '.count($this->selected).' selected to a test' : 'Add selected to a test')
            ->icon('heroicon-m-check-circle')
            ->disabled(fn () => ! count($this->selected))
            ->modalHeading('Add the selected questions to a test')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Add questions')
            ->schema([
                Select::make('test_id')->label('Test')->required()->searchable()->preload()
                    ->default(fn () => $this->getRecord()->test_id)
                    ->options(fn () => Test::with('course')->withCount('questions')->orderBy('title')->get()
                        ->mapWithKeys(fn (Test $t) => [$t->id => "{$t->title} — {$t->course->title} ({$t->questions_count} questions)"]))
                    ->helperText('They are added after the questions already in the test. You can create a new test with the + button.')
                    ->createOptionModalHeading('New test')
                    ->createOptionForm([
                        Select::make('course_id')->label('Course')->required()->searchable()->options(\App\Models\Course::pluck('title', 'id')),
                        TextInput::make('title')->required()->maxLength(150),
                        Radio::make('kind')->label('Type')->inline()->required()->default('practice')
                            ->options(['practice' => 'Practice test', 'mock' => 'Mock test']),
                        TextInput::make('duration_minutes')->label('Duration (minutes)')->numeric()->required()->default(60)->minValue(1),
                        TextInput::make('marks_correct')->label('Marks for a correct answer')->numeric()->required()->default(2),
                        TextInput::make('marks_wrong')->label('Marks deducted for a wrong answer')->numeric()->step(0.25)->required()->default(0.25),
                    ])
                    ->createOptionUsing(fn (array $data) => Test::create($data + ['is_published' => true])->getKey()),
            ])
            ->action(function (array $data) {
                $test = Test::findOrFail($data['test_id']);
                $counts = app(QuestionImportService::class)->commit($this->getRecord(), array_map('intval', $this->selected), $test);
                $this->selected = [];

                $parts = array_filter([
                    $counts['added'] ? "{$counts['added']} new" : null,
                    $counts['reused'] ? "{$counts['reused']} already in the question bank (reused)" : null,
                    $counts['blocked'] ? "{$counts['blocked']} skipped because they have errors" : null,
                    $counts['over_limit'] ? "{$counts['over_limit']} not added: a test holds at most ".QuestionImportService::MAX_PER_TEST.' questions' : null,
                ]);
                Notification::make()->title('Questions added to “'.$test->title.'”')->body(implode(' · ', $parts))->success()
                    ->actions([Action::make('open')->label('Open the test')->url(TestResource::getUrl('questions', ['record' => $test]))])->send();
            });
    }

    // ------------------------------------------------------------------ draft actions

    /** Fix a draft in the question editor. arguments: id */
    public function editDraftAction(): Action
    {
        return Action::make('editDraft')->slideOver()->modalWidth('4xl')->modalHeading('Edit question')->modalSubmitActionLabel('Save changes')
            ->schema(QuestionForm::schema())
            ->fillForm(fn (array $arguments) => $this->draft($arguments['id'])->toForm())
            ->action(function (array $data, array $arguments) {
                $draft = $this->draft($arguments['id']);
                $draft->fill($data);
                // After a manual edit the warnings are worked out again from the new content
                $draft->warnings = app(ImportValidator::class)->recheck($draft->only(['body', 'option_1', 'option_2', 'option_3', 'option_4', 'correct', 'explanation']));
                $draft->save();
            });
    }

    public function toggleSkip(int $id): void
    {
        $draft = $this->draft($id);
        if ($draft->status !== 'added') {
            $draft->update(['status' => $draft->status === 'skipped' ? 'pending' : 'skipped']);
            $this->selected = array_values(array_diff($this->selected, [(string) $id, $id]));
        }
    }

    public function skipSelected(): void
    {
        $this->getRecord()->drafts()->whereIn('id', $this->selected)->where('status', 'pending')->update(['status' => 'skipped']);
        $this->selected = [];
    }

    /** Ticks every pending question in the current filter. `$cleanOnly` leaves out the ones with warnings. */
    public function selectAll(bool $cleanOnly = false): void
    {
        $this->selected = $this->filter($this->getRecord()->drafts()->get())
            ->filter(fn ($d) => $d->status === 'pending' && $d->severity() !== 'error' && (! $cleanOnly || $d->severity() === 'ok'))
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function loadMore(): void
    {
        $this->limit += 30;
    }

    private function draft(int $id): ImportQuestion
    {
        return $this->getRecord()->drafts()->findOrFail($id);
    }
}
