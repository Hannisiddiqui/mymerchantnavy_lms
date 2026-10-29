<?php

namespace App\Filament\Resources\PendingChangeResource\Pages;

use App\Filament\Resources\PendingChangeResource;
use Filament\Resources\Pages\ListRecords;

class ListPendingChanges extends ListRecords
{
    protected static string $resource = PendingChangeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
