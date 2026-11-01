<?php

namespace App\Filament\Pages;

use App\Models\Question;
use App\Models\Test;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Report: for each question of a test, how many students got it right, wrong or skipped.
 * Pick a test in the filter. Counts include every attempt that was submitted.
 */
class QuestionAnalysis extends Page implements HasTable
{
    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin && ! $admin->isStudentPreview();
    }

    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Question analysis';

    protected string $view = 'filament.pages.question-analysis';

    public function table(Table $table): Table
    {
        // Counts are limited to the selected test's attempts
        $testId = $this->tableFilters['test_id']['value'] ?? null;
        $answers = fn (string $extra) => "(select count(*) from attempt_answers aa
            join test_attempts ta on ta.id = aa.test_attempt_id and ta.submitted_at is not null"
            .($testId ? ' and ta.test_id = '.(int) $testId : '')."
            where aa.question_id = questions.id {$extra})";

        return $table
            ->query(fn (): Builder => Question::query()
                ->when($testId, fn ($q) => $q->whereHas('tests', fn ($t) => $t->whereKey($testId)), fn ($q) => $q->whereRaw('1 = 0'))
                ->select('questions.*')
                ->selectRaw($answers('and aa.selected_option_id is not null and exists (select 1 from question_options qo where qo.id = aa.selected_option_id and qo.is_correct = true)').' as correct_count')
                ->selectRaw($answers('and aa.selected_option_id is not null and exists (select 1 from question_options qo where qo.id = aa.selected_option_id and qo.is_correct = false)').' as wrong_count')
                ->selectRaw($answers('and aa.selected_option_id is null').' as skipped_count'))
            ->columns([
                TextColumn::make('body')->label('Question')->wrap()->extraAttributes(['class' => 'cb-math'])
                    ->formatStateUsing(fn ($state) => Str::limit(strip_tags($state), 100)), // $…$ formulas are drawn by the admin KaTeX script
                TextColumn::make('correct_count')->label('Correct')->color('success')->sortable(),
                TextColumn::make('wrong_count')->label('Wrong')->color('danger')->sortable(),
                TextColumn::make('skipped_count')->label('Skipped')->sortable(),
                TextColumn::make('accuracy')->label('Accuracy')
                    ->state(fn ($record) => ($record->correct_count + $record->wrong_count) ? round($record->correct_count / ($record->correct_count + $record->wrong_count) * 100).'%' : '—'),
            ])
            ->filters([
                SelectFilter::make('test_id')->label('Test')->options(fn () => Test::orderBy('title')->pluck('title', 'id'))
                    ->searchable()->query(fn ($query) => $query),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->emptyStateHeading('Choose a test')
            ->emptyStateDescription('Select a test above to see how students did on each question.')
            ->paginated(false);
    }
}
