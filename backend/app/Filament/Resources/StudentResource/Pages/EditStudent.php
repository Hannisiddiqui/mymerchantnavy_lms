<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
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
                ->modalHeading('Delete this student?')
                ->modalDescription('This permanently removes the student with their test results, notes and progress. It cannot be undone. To only stop them logging in, use "Mark inactive" in the student list instead.'),
        ];
    }
}
