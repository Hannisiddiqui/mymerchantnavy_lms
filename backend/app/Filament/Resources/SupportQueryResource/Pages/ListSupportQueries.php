<?php

namespace App\Filament\Resources\SupportQueryResource\Pages;

use App\Filament\Resources\SupportQueryResource;
use Filament\Resources\Pages\ListRecords;

class ListSupportQueries extends ListRecords
{
    protected static string $resource = SupportQueryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
