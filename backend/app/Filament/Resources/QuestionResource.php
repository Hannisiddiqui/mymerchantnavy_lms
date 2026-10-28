<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages;
use App\Filament\Support\QuestionForm;
use App\Models\Question;
use App\Models\Test;
use App\Services\Tests\QuestionService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The question bank: every question ever written. A question can be used in many tests.
 * Find questions with the search box (searches the question, its options and its solution) and the filters;
 * select several rows to add them to a test in one go.
 */
class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(QuestionForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('options')->withCount('tests'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('body')->label('Question')->html()->wrap()->extraAttributes(['class' => 'cb-math'])
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::limit(strip_tags($state), 160))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $w) => $w
                        ->whereLike('body', "%{$search}%")
                        ->orWhereLike('explanation', "%{$search}%")
                        ->orWhereHas('options', fn (Builder $o) => $o->whereLike('body', "%{$search}%")))),
                TextColumn::make('answer')->label('Answer')->badge()->color('success')
                    ->state(fn (Question $record) => app(QuestionService::class)->correctLetter($record)),
                TextColumn::make('subject.title')->label('Subject')->toggleable(),
                TextColumn::make('difficulty')->badge()->placeholder('—')->color(fn ($state) => match ($state) {'easy' => 'success', 'hard' => 'danger', default => 'warning'}),
                TextColumn::make('tests_count')->label('In tests')->sortable()
                    ->tooltip(fn (Question $record) => $record->tests->pluck('title')->implode(', ') ?: 'Not used in any test'),
                TextColumn::make('updated_at')->label('Updated')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Search question, option or solution…')
            ->filters([
                SelectFilter::make('difficulty')->options(['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard']),
                SelectFilter::make('subject_id')->label('Subject')->relationship('subject', 'title'),
                SelectFilter::make('in_test')->label('Used in test')->options(fn () => Test::orderBy('title')->pluck('title', 'id'))->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $id) => $q->whereHas('tests', fn ($t) => $t->whereKey($id)))),
                Filter::make('unused')->label('Not used in any test')->toggle()->query(fn (Builder $query) => $query->doesntHave('tests')),
            ])
            ->recordActions([
                EditAction::make()->slideOver()->modalWidth('4xl')
                    ->fillForm(fn (Question $record) => app(QuestionService::class)->toForm($record))
                    ->using(fn (Question $record, array $data) => app(QuestionService::class)->save($data, $record)),
                Action::make('duplicate')->label('Duplicate')->icon('heroicon-o-document-duplicate')->color('gray')
                    ->action(function (Question $record) {
                        app(QuestionService::class)->duplicate($record);
                        Notification::make()->title('Question duplicated')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('addToTest')->label('Add to a test')->icon('heroicon-o-plus-circle')->color('success')
                        ->schema([Select::make('test_id')->label('Test')->required()->searchable()->options(fn () => Test::with('course')->get()->mapWithKeys(fn ($t) => [$t->id => "{$t->title} ({$t->course->title})"]))])
                        ->action(function (Collection $records, array $data) {
                            $test = Test::findOrFail($data['test_id']);
                            $have = $test->questions()->pluck('questions.id');
                            $new = $records->pluck('id')->diff($have)->values();
                            $start = (DB::table('test_questions')->where('test_id', $test->id)->max('position') ?? -1) + 1;
                            $test->questions()->attach($new->mapWithKeys(fn ($id, $i) => [$id => ['position' => $start + $i]])->all());
                            Notification::make()->title("{$new->count()} question(s) added to “{$test->title}”")->success()
                                ->actions([\Filament\Actions\Action::make('open')->label('Open test')->url(TestResource::getUrl('questions', ['record' => $test]))])->send();
                        })->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([15, 30, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
