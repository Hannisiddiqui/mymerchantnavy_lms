<?php

namespace App\Filament\Resources;

use App\Enums\TestKind;
use App\Filament\Resources\TestResource\Pages;
use App\Models\Test;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Practice tests (placed inside chapters) and mock tests (placed in the course between subjects). */
class TestResource extends Resource
{
    protected static ?string $model = Test::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Test details')->columns(2)->schema([
                TextInput::make('title')->required()->maxLength(150)->columnSpanFull(),
                Select::make('course_id')->label('Course')->relationship('course', 'title')->required()->searchable()->preload(),
                Select::make('kind')->label('Type')->options(['practice' => 'Practice test (inside a chapter)', 'mock' => 'Mock test (between subjects)'])->required()->default('practice'),
                TextInput::make('duration_minutes')->label('Duration (minutes)')->numeric()->required()->minValue(1)->default(90),
                Toggle::make('is_published')->label('Published')->inline(false)->helperText('Unpublished tests are hidden from students.'),
            ]),
            Section::make('Marking')->columns(2)->schema([
                TextInput::make('marks_correct')->label('Marks for a correct answer')->numeric()->required()->default(2),
                TextInput::make('marks_wrong')->label('Marks deducted for a wrong answer')->numeric()->step(0.25)->required()->default(0.25)->helperText('Enter 0 for no negative marking.'),
            ]),
            Section::make('Extra instructions')->collapsed()->schema([
                RichEditor::make('instructions')->hiddenLabel()
                    ->helperText('Optional. The standard instructions (timer, palette, marking) are always shown automatically.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->weight('bold'),
                TextColumn::make('course.title')->label('Course')->sortable(),
                TextColumn::make('kind')->badge()->color(fn ($state) => $state === TestKind::Mock ? 'primary' : 'success')
                    ->formatStateUsing(fn ($state) => $state === TestKind::Mock ? 'Mock' : 'Practice'),
                TextColumn::make('questions_count')->counts('questions')->label('Questions'),
                TextColumn::make('duration_minutes')->label('Minutes'),
                TextColumn::make('attempts_count')->counts('attempts')->label('Attempts'),
                IconColumn::make('is_published')->label('Live')->boolean(),
            ])
            ->filters([
                SelectFilter::make('course_id')->label('Course')->relationship('course', 'title'),
                SelectFilter::make('kind')->options(['practice' => 'Practice', 'mock' => 'Mock']),
            ])
            ->recordActions([
                Action::make('questions')->label('Manage questions')->icon('heroicon-o-queue-list')->color('success')
                    ->url(fn (Test $record) => static::getUrl('questions', ['record' => $record])),
                EditAction::make()->label('Settings'),
                DeleteAction::make()->modalDescription('The test, the attempts made on it and the questions that belong only to this test are deleted. Questions another test also uses are kept.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->modalDescription('The selected tests, their attempts and the questions that belong only to them are deleted. Questions another test also uses are kept.'),
                ]),
            ])
            ->recordUrl(fn (Test $record) => static::getUrl('questions', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTests::route('/'),
            'create' => Pages\CreateTest::route('/create'),
            'edit' => Pages\EditTest::route('/{record}/edit'),
            'questions' => Pages\ManageQuestions::route('/{record}/questions'),
        ];
    }
}
