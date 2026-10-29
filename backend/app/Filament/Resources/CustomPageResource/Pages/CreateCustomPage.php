<?php

namespace App\Filament\Resources\CustomPageResource\Pages;

use App\Filament\Resources\CustomPageResource;
use App\Models\ActivityLog;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCustomPage extends CreateRecord
{
    protected static string $resource = CustomPageResource::class;

    protected function afterCreate(): void
    {
        $admin = Auth::guard('admin')->user();
        ActivityLog::record(
            event: 'custom_page.created',
            description: "Admin created new custom page '{$this->record->title}' (/pages/{$this->record->slug}).",
            properties: [
                'id' => $this->record->id,
                'title' => $this->record->title,
                'slug' => $this->record->slug,
            ],
            admin: $admin
        );
    }
}
