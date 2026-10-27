<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use App\Models\ActivityLog;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('logoutDevices')->label('Log out all devices')->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('gray')->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->tokens()->delete();
                    Notification::make()->title('Student logged out everywhere')->success()->send();
                }),
            DeleteAction::make()
                ->visible(fn () => auth('admin')->user()?->isSuperAdmin() ?? false)
                ->modalHeading('Delete this student?')
                ->modalDescription('This permanently removes the student with their test results, notes and progress. Only Super Admin can perform this action.')
                ->before(function ($record) {
                    ActivityLog::record(
                        event: 'student.deleted',
                        description: "Super Admin deleted student {$record->email} (ID: {$record->id}, Name: {$record->name})",
                        subject: null,
                        properties: [
                            'student_id' => $record->id,
                            'student_email' => $record->email,
                            'student_name' => $record->name,
                        ]
                    );
                }),
        ];
    }
}
