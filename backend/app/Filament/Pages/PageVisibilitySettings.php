<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Setting;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Super Admin Page Visibility Settings.
 * Hide or show navigation pages on the Student LMS.
 */
class PageVisibilitySettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Student Page Visibility';

    protected static ?string $navigationLabel = 'Page Visibility';

    protected string $view = 'filament.pages.page-visibility-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'page_visibility_my_courses' => filter_var(Setting::get('page_visibility_my_courses', '1'), FILTER_VALIDATE_BOOLEAN),
            'page_visibility_batches' => filter_var(Setting::get('page_visibility_batches', '1'), FILTER_VALIDATE_BOOLEAN),
            'page_visibility_mock_tests' => filter_var(Setting::get('page_visibility_mock_tests', '1'), FILTER_VALIDATE_BOOLEAN),
            'page_visibility_performance' => filter_var(Setting::get('page_visibility_performance', '1'), FILTER_VALIDATE_BOOLEAN),
            'page_visibility_results' => filter_var(Setting::get('page_visibility_results', '1'), FILTER_VALIDATE_BOOLEAN),
            'page_visibility_notes' => filter_var(Setting::get('page_visibility_notes', '1'), FILTER_VALIDATE_BOOLEAN),
            'page_visibility_profile' => filter_var(Setting::get('page_visibility_profile', '1'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Student LMS Sidebar Navigation Controls')
                    ->description('Super Admin can show or hide specific sections on the student portal. When a section is toggled OFF, it will not appear in the student sidebar menu.')
                    ->schema([
                        Toggle::make('page_visibility_my_courses')
                            ->label('My Courses (/my-courses)')
                            ->helperText('Enrolled courses, subjects, video classes, and curriculum reader.')
                            ->default(true),

                        Toggle::make('page_visibility_batches')
                            ->label('Batches Store (/batches)')
                            ->helperText('PhysicsWallah-style sellable courses & batches store.')
                            ->default(true),

                        Toggle::make('page_visibility_mock_tests')
                            ->label('Mock Tests (/mock-tests)')
                            ->helperText('All-India simulated full-length and practice test catalog.')
                            ->default(true),

                        Toggle::make('page_visibility_performance')
                            ->label('Performance Analytics (/performance)')
                            ->helperText('Student progress charts, radar breakdowns, and test streaks.')
                            ->default(true),

                        Toggle::make('page_visibility_results')
                            ->label('Test Results (/results)')
                            ->helperText('Historical test attempt reports, answer keys, and scorecards.')
                            ->default(true),

                        Toggle::make('page_visibility_notes')
                            ->label('My Notes (/notes)')
                            ->helperText('Personal student notes and bookmarks across reader documents.')
                            ->default(true),

                        Toggle::make('page_visibility_profile')
                            ->label('Student Profile (/profile)')
                            ->helperText('Student account details, exam target, and device management.')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public function save(): void
    {
        $formData = $this->form->getState();

        $keys = [
            'page_visibility_my_courses',
            'page_visibility_batches',
            'page_visibility_mock_tests',
            'page_visibility_performance',
            'page_visibility_results',
            'page_visibility_notes',
            'page_visibility_profile',
        ];

        foreach ($keys as $key) {
            Setting::set($key, ! empty($formData[$key]) ? '1' : '0', 'Student LMS page visibility toggle.');
        }

        ActivityLog::record(
            event: 'page_visibility.updated',
            description: 'Super Admin updated student page visibility settings.',
            properties: $formData
        );

        Notification::make()
            ->title('Page Visibility Updated')
            ->body('Student LMS navigation menu updated successfully.')
            ->success()
            ->send();
    }
}

