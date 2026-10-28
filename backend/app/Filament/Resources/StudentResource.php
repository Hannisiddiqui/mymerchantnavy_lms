<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers\AttemptsRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\CoursesRelationManager;
use App\Filament\Support\AssignCourseAction;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'student';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

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
                TextColumn::make('name')
                    ->label('Student')
                    ->searchable(['name', 'email', 'phone'])
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email . ($record->phone ? " • {$record->phone}" : ''))
                    ->formatStateUsing(fn ($state, User $record) => $state ?: ($record->email)),

                TextColumn::make('purchase_status')
                    ->label('Purchase')
                    ->badge()
                    ->state(function (User $record) {
                        $enrollments = $record->enrollments;
                        if ($enrollments->isEmpty()) {
                            return 'No Course';
                        }
                        if ($enrollments->contains('purchase_type', 'paid')) {
                            return 'Paid';
                        }
                        if ($enrollments->contains('purchase_type', 'free')) {
                            return 'Free';
                        }

                        return 'Assigned';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Free' => 'info',
                        'Assigned' => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('enrolled_courses')
                    ->label('Course Access')
                    ->state(function (User $record) {
                        $enrollments = $record->enrollments()->with('course')->get();
                        if ($enrollments->isEmpty()) {
                            return 'None';
                        }

                        return $enrollments->map(function (Enrollment $enr) {
                            $title = $enr->course?->title ?? 'Course';
                            $status = $enr->isExpired() ? 'Expired' : ($enr->expires_at ? 'Exp: ' . $enr->expires_at->format('d M y') : 'Active');

                            return "{$title} ({$status})";
                        })->implode("\n");
                    })
                    ->wrap()
                    ->badge()
                    ->color(fn ($state) => str_contains((string) $state, 'Expired') ? 'danger' : 'primary'),

                TextColumn::make('target_exam')
                    ->label('Target Exam')
                    ->toggleable()
                    ->placeholder('—')
                    ->description(fn (User $record) => $record->attempt_year ? "Year: {$record->attempt_year}" : null),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('purchase_status')
                    ->label('Purchase Type')
                    ->options([
                        'paid' => 'Paid Students (Has Paid Purchase)',
                        'free' => 'Free Students (Has Free Enrollment)',
                        'assigned' => 'Admin Assigned Students',
                        'no_course' => 'Students Without a Course',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value'] ?? null) {
                            'paid' => $query->withPaidEnrollment(),
                            'free' => $query->withFreeEnrollment(),
                            'assigned' => $query->whereHas('enrollments', fn ($q) => $q->where('purchase_type', 'assigned')),
                            'no_course' => $query->withoutCourses(),
                            default => $query,
                        };
                    }),

                SelectFilter::make('enrollment_status')
                    ->label('Access Status')
                    ->options([
                        'active' => 'Active Enrollments',
                        'expired' => 'Expired Enrollments',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value'] ?? null) {
                            'active' => $query->withActiveEnrollment(),
                            'expired' => $query->withExpiredEnrollment(),
                            default => $query,
                        };
                    }),

                SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(Course::orderBy('title')->pluck('title', 'id'))
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('enrollments', fn ($q) => $q->where('course_id', $data['value']))
                        : $query),

                Filter::make('registration_range')
                    ->form([
                        DatePicker::make('registered_from')->label('Registered From'),
                        DatePicker::make('registered_until')->label('Registered Until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['registered_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['registered_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),

                TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->recordActions([
                Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalHeading(fn (User $record) => "Student Overview: " . ($record->name ?: $record->email))
                    ->modalContent(fn (User $record) => view('filament.resources.student-details', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('3xl'),

                AssignCourseAction::make(fn (Action $action) => $action->getRecord()),

                EditAction::make(),

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
