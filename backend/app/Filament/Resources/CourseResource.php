<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Filament\Resources\CourseResource\RelationManagers\StudentsRelationManager;
use App\Models\Course;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 1;

    public static function canDelete(Model $record): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(150)->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
            TextInput::make('slug')->required()->unique(ignoreRecord: true)->helperText('Used in the course link.'),
            TextInput::make('wordpress_product_id')->label('WordPress product ID')->maxLength(191)
                ->helperText('The id the WordPress shop sends when this course is bought. Several ids can be separated by commas. Leave empty if it is not sold there.'),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            FileUpload::make('thumbnail_path')->label('Thumbnail')->image()->disk('public')->directory('course-thumbnails'),
            Toggle::make('is_active')->label('Active')->default(true)->helperText('Inactive courses are hidden from students.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->weight('bold'),
                TextColumn::make('subjects_count')->counts('subjects')->label('Subjects'),
                TextColumn::make('tests_count')->counts('tests')->label('Tests'),
                TextColumn::make('students_count')->counts('students')->label('Students'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                Action::make('curriculum')
                    ->label('Build course')
                    ->icon('heroicon-o-squares-plus')
                    ->color('primary')
                    ->url(fn (Course $record) => static::getUrl('curriculum', ['record' => $record])),
                EditAction::make(),
            ])
            ->recordUrl(fn (Course $record) => static::getUrl('curriculum', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [StudentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
            'curriculum' => Pages\ManageCurriculum::route('/{record}/curriculum'),
        ];
    }
}
