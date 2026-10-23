<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use App\Models\Course;
use App\Models\User;
use App\Services\Courses\EnrollmentService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/** Students who can access this course. Admins assign/remove access here (or from the Students page). */
class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $title = 'Students with access';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('name')->searchable()->placeholder('— profile not completed —'),
                TextColumn::make('phone')->label('Mobile')->toggleable()->placeholder('—'),
                TextColumn::make('pivot.assigned_at')->label('Assigned on')->dateTime('d M Y, h:i A'),
            ])
            ->headerActions([
                Action::make('assign')->label('Assign students')->icon('heroicon-o-user-plus')
                    ->schema([
                        Select::make('students')->label('Students')->multiple()->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::query()
                                ->where(fn ($q) => $q->whereLike('email', "%{$search}%")->orWhereLike('phone', "%{$search}%")->orWhereLike('name', "%{$search}%"))
                                ->limit(30)->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->loginId()} — ".($u->name ?: 'no name yet')]))
                            ->helperText('Search by email, mobile number or name. The student gets a notification.'),
                    ])
                    ->action(function (array $data) {
                        /** @var Course $course */
                        $course = $this->getOwnerRecord();
                        $count = 0;
                        foreach (User::findMany($data['students']) as $student) {
                            $count += app(EnrollmentService::class)->assign($student, $course, auth('admin')->user());
                        }
                        Notification::make()->title("Course assigned to {$count} student(s)")->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('remove')->label('Remove access')->icon('heroicon-o-x-mark')->color('danger')->requiresConfirmation()
                    ->action(fn (User $record) => app(EnrollmentService::class)->remove($record, $this->getOwnerRecord())),
            ])
            ->toolbarActions([
                BulkAction::make('remove')->label('Remove access')->color('danger')->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn (User $u) => app(EnrollmentService::class)->remove($u, $this->getOwnerRecord()))),
            ]);
    }
}
