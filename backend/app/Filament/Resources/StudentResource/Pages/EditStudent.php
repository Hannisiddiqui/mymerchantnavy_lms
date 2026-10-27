<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions\Action;
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
        ];
    }
}
