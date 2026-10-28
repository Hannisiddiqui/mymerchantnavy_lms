<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Services\Tests\QuestionService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditQuestion extends EditRecord
{
    protected static string $resource = QuestionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return app(QuestionService::class)->toForm($this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(QuestionService::class)->save($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
