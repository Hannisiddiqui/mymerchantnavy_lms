<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers\AttemptsRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\CoursesRelationManager;
use App\Filament\Support\AssignCourseAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StudentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'student';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')->label('Email address')->email()->required()->unique(ignoreRecord: true)
                ->helperText('This is what the student logs in with: a one-time code is emailed to it.'),
            TextInput::make('phone')->label('Mobile number (optional)')->regex('/^[6-9]\d{9}$/')->unique(ignoreRecord: true)
                ->helperText('10 digits. Only used to contact the student.'),
            Toggle::make('is_active')->label('Account active')->default(true)->inline(false)
                ->helperText('Switch off to block this student from logging in.'),
            TextInput::make('name'),
            TextInput::make('nickname'),
            Select::make('target_exam')->options(fn () => collect(['IMU-CET', 'Company Sponsorship Exam', 'DNS (Diploma in Nautical Science)', 'B.Tech Marine Engineering', 'GP Rating', 'ETO (Electro-Technical Officer)', 'Other'])->mapWithKeys(fn ($v) => [$v => $v])),
            TextInput::make('attempt_year')->numeric(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->weight('bold')->description(fn ($record) => $record->email_verified_at ? null : 'not verified yet'),
                TextColumn::make('name')->searchable()->placeholder('— not completed —'),
                TextColumn::make('phone')->label('Mobile')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('target_exam')->label('Target exam')->toggleable(),
                TextColumn::make('attempt_year')->label('Year')->toggleable(),
                TextColumn::make('courses_count')->counts('courses')->label('Courses')->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Registered')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('has_course')->label('Has a course')
                    ->queries(true: fn ($q) => $q->has('courses'), false: fn ($q) => $q->doesntHave('courses')),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                AssignCourseAction::make(fn (Action $action) => $action->getRecord()),
                EditAction::make(),
                // The only way to switch a student off: students are never deleted.
                Action::make('deactivate')->label('Mark inactive')->icon('heroicon-o-no-symbol')->color('danger')
                    ->visible(fn (User $record) => $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Mark this student inactive?')
                    ->modalDescription('They are logged out of every device and cannot log in again until you mark them active. Their results, notes and history are kept.')
                    ->action(function (User $record) {
                        $record->update(['is_active' => false]);
                        Notification::make()->title('Student marked inactive')->success()->send();
                    }),
                Action::make('activate')->label('Mark active')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (User $record) => ! $record->is_active)
                    ->action(function (User $record) {
                        $record->update(['is_active' => true]);
                        Notification::make()->title('Student marked active')->success()->send();
                    }),
            ]);
    }

    /** Students can be marked active or inactive, never deleted. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [CoursesRelationManager::class, AttemptsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
