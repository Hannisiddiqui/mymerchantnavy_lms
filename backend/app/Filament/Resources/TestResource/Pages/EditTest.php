<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Filament\Resources\TestResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** Test settings (title, duration, marking). Questions are managed on ManageQuestions. */
class EditTest extends EditRecord
{
    protected static string $resource = TestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('questions')->label('Manage questions')->icon('heroicon-o-queue-list')->color('success')
                ->url(fn () => static::getResource()::getUrl('questions', ['record' => $this->getRecord()])),
            DeleteAction::make()
                ->visible(fn () => auth('admin')->user()?->isSuperAdmin() ?? false)
                ->modalDescription('The test, the attempts made on it and the questions that belong only to this test are deleted. Questions another test also uses are kept.'),
        ];
    }
}
