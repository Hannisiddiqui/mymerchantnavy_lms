<?php

namespace App\Filament\Resources\BatchResource\Pages;

use App\Filament\Resources\BatchResource;
use App\Models\ActivityLog;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListBatches extends ListRecords
{
    protected static string $resource = BatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('batchDisplaySettings')
                ->label('Batch Store & Category Settings')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading('Batch Page Display & Category Management')
                ->modalDescription('Control whether the upper category navigation bar is shown on the student LMS Batches store, and customize the category filter tabs.')
                ->modalWidth('xl')
                ->fillForm(function () {
                    $savedCats = Setting::get('batch_custom_categories');
                    $categories = $savedCats ? json_decode($savedCats, true) : null;
                    if (! is_array($categories) || empty($categories)) {
                        $categories = ['All Batches', 'IMU-CET', 'DNS Sponsorship', 'G.P. Rating', 'B.Tech Marine'];
                    }

                    return [
                        'show_categories' => Setting::get('batch_page_show_categories', '1') !== '0',
                        'categories' => $categories,
                    ];
                })
                ->form([
                    Toggle::make('show_categories')
                        ->label('Show Upper Category Filter Bar on Student Batches Store')
                        ->helperText('When switched off, the category navigation pills above the batch cards will be completely hidden from students.')
                        ->default(true),

                    TagsInput::make('categories')
                        ->label('Batch Categories & Filter Tabs')
                        ->placeholder('Type category name and press Enter...')
                        ->helperText('Define your custom categories (e.g. "All Batches", "IMU-CET", "DNS Sponsorship", "NDA 2027", "Deck Cadet"). Batches can be assigned to any of these categories.')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $show = (bool) ($data['show_categories'] ?? true);
                    $cats = array_values(array_filter((array) ($data['categories'] ?? [])));
                    if (empty($cats)) {
                        $cats = ['All Batches'];
                    }

                    Setting::set('batch_page_show_categories', $show ? '1' : '0', 'Toggle upper category filter bar on /batches');
                    Setting::set('batch_custom_categories', json_encode($cats), 'Custom batch filter categories');

                    $admin = Auth::guard('admin')->user();
                    ActivityLog::record(
                        event: 'batch.display_settings_updated',
                        description: "Admin updated batch store settings (Show Upper Nav: ".($show ? 'YES' : 'NO').", Categories: ".implode(', ', $cats).").",
                        properties: [
                            'show_categories' => $show,
                            'categories' => $cats,
                        ],
                        admin: $admin
                    );

                    Notification::make()
                        ->title('Batch Store Settings Saved')
                        ->body('Upper navigation visibility and custom categories updated successfully.')
                        ->success()
                        ->send();
                }),

            CreateAction::make()->label('New Batch'),
        ];
    }
}


