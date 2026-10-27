<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use App\Models\Admin;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Admin $record) => auth('admin')->user()?->isSuperAdmin() && ! $record->isSuperAdmin() && ! $record->is(auth('admin')->user()) && Admin::count() > 1),
        ];
    }
}
