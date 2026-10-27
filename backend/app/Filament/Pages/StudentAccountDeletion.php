<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Super-Admin-only page to safely and permanently delete student accounts.
 * Every deletion is audited and recorded in the Activity Log.
 */
class StudentAccountDeletion extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-minus';

    protected static string|\UnitEnum|null $navigationGroup = 'Super Admin';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Student Account Deletion';

    protected static ?string $navigationLabel = 'Delete Student Accounts';

    protected string $view = 'filament.pages.student-account-deletion';

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()->withCount(['courses', 'attempts']))
            ->columns([
                TextColumn::make('email')
                    ->label('Student Email')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email_verified_at ? 'Verified' : 'Unverified'),
                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('— no name —'),
                TextColumn::make('phone')
                    ->label('Mobile')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('target_exam')
                    ->label('Target Exam')
                    ->toggleable(),
                TextColumn::make('courses_count')
                    ->label('Courses')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('attempts_count')
                    ->label('Attempts')
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')->label('Account Active'),
                TernaryFilter::make('has_courses')
                    ->label('Enrolled in Courses')
                    ->queries(
                        true: fn ($q) => $q->has('courses'),
                        false: fn ($q) => $q->doesntHave('courses'),
                    ),
            ])
            ->recordActions([
                Action::make('deleteStudent')
                    ->label('Delete Account')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Permanently Delete Student Account')
                    ->modalDescription('Warning: This action CANNOT be undone. The student will be permanently deleted along with all their test attempts, exam answers, notes, submissions, and active login sessions.')
                    ->modalSubmitActionLabel('Yes, Permanently Delete')
                    ->form([
                        Select::make('reason')
                            ->label('Reason for Deletion')
                            ->required()
                            ->options([
                                'Requested by student (GDPR / Data deletion)' => 'Requested by student (GDPR / Data deletion)',
                                'Fraudulent account / suspicious activity' => 'Fraudulent account / suspicious activity',
                                'Duplicate account' => 'Duplicate account',
                                'Terms of service violation' => 'Terms of service violation',
                                'Test account cleanup' => 'Test account cleanup',
                                'Other' => 'Other',
                            ]),
                        Textarea::make('notes')
                            ->label('Additional Details / Audit Notes')
                            ->placeholder('Optional details for audit log...')
                            ->rows(3),
                    ])
                    ->action(function (User $record, array $data) {
                        $email = $record->email;
                        $name = $record->name;
                        $id = $record->id;
                        $reason = $data['reason'] ?? 'Not specified';
                        $notes = $data['notes'] ?? '';

                        // Revoke all tokens
                        $record->tokens()->delete();

                        // Log activity BEFORE deleting user record
                        ActivityLog::record(
                            event: 'student.deleted',
                            description: "Super Admin permanently deleted student {$email} (Name: {$name}, ID: {$id}). Reason: {$reason}".($notes ? " | Notes: {$notes}" : ''),
                            subject: null,
                            properties: [
                                'student_id' => $id,
                                'email' => $email,
                                'name' => $name,
                                'reason' => $reason,
                                'notes' => $notes,
                            ]
                        );

                        // Delete student record (cascade deletes enrollments, notes, attempts)
                        $record->delete();

                        Notification::make()
                            ->title('Student Account Deleted')
                            ->body("Student {$email} has been permanently removed.")
                            ->danger()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulkDelete')
                    ->label('Delete Selected Students')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Permanently Delete Selected Students?')
                    ->modalDescription('Warning: All selected students will be permanently removed along with all their history and sessions. This action will be audited in the Activity Log.')
                    ->form([
                        Select::make('reason')
                            ->label('Reason for Bulk Deletion')
                            ->required()
                            ->options([
                                'Bulk cleanup of test / inactive accounts' => 'Bulk cleanup of test / inactive accounts',
                                'Fraud / spam accounts cleanup' => 'Fraud / spam accounts cleanup',
                                'Policy violation' => 'Policy violation',
                                'Other' => 'Other',
                            ]),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $count = $records->count();
                        $reason = $data['reason'] ?? 'Bulk cleanup';

                        foreach ($records as $student) {
                            /** @var User $student */
                            $student->tokens()->delete();

                            ActivityLog::record(
                                event: 'student.deleted',
                                description: "Super Admin bulk-deleted student {$student->email} (ID: {$student->id}). Reason: {$reason}",
                                subject: null,
                                properties: [
                                    'student_id' => $student->id,
                                    'email' => $student->email,
                                    'reason' => $reason,
                                ]
                            );

                            $student->delete();
                        }

                        Notification::make()
                            ->title('Selected Students Deleted')
                            ->body("Successfully removed {$count} student account(s).")
                            ->danger()
                            ->send();
                    }),
            ]);
    }
}
