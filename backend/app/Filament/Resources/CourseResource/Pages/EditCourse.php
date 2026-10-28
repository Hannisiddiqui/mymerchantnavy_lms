<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** Course details (title, description, thumbnail). The content is edited on ManageCurriculum. */
class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('curriculum')->label('Build course content')->icon('heroicon-o-squares-plus')
                ->url(fn () => static::getResource()::getUrl('curriculum', ['record' => $this->getRecord()])),
            DeleteAction::make(),
        ];
    }
}
