<?php

namespace App\Filament\Resources\CustomPageResource\Pages;

use App\Filament\Resources\CustomPageResource;
use App\Models\ActivityLog;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditCustomPage extends EditRecord
{
    protected static string $resource = CustomPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $admin = Auth::guard('admin')->user();
        ActivityLog::record(
            event: 'custom_page.updated',
            description: "Admin updated custom page '{$this->record->title}' (/pages/{$this->record->slug}).",
            properties: [
                'id' => $this->record->id,
                'title' => $this->record->title,
                'slug' => $this->record->slug,
            ],
            admin: $admin
        );
    }
}

