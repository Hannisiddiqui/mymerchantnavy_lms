<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Super-Admin-only page to configure session limits and security policies.
 */
class SessionLimits extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static string|\UnitEnum|null $navigationGroup = 'Super Admin';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Session Limits & Security';

    protected static ?string $navigationLabel = 'Session Limits';

    protected string $view = 'filament.pages.session-limits';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'device_limit_enabled' => filter_var(Setting::get('device_limit_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'student_max_devices' => (int) Setting::get('student_max_devices', config('lms.max_devices', 2) ?: 2),
            'tab_limit_enabled' => filter_var(Setting::get('tab_limit_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'student_max_tabs' => (int) Setting::get('student_max_tabs', 2),
            'admin_session_lifetime_minutes' => (int) Setting::get('admin_session_lifetime_minutes', 120),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Part 1: Student Device Limits (Cross-Device)')
                    ->description('Manage multi-device logins to prevent student account credentials from being passed around.')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->components([
                        Toggle::make('device_limit_enabled')
                            ->label('Enforce Concurrent Device Limit')
                            ->helperText('When enabled, students can only be logged into the specified number of physical devices at once. When disabled, devices are unlimited.')
                            ->default(true)
                            ->live(),

                        TextInput::make('student_max_devices')
                            ->label('Maximum Concurrent Devices Allowed')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->default(2)
                            ->suffix('device(s)')
                            ->required()
                            ->visible(fn ($get) => (bool) $get('device_limit_enabled'))
                            ->helperText('Recommended: 2 devices (Phone + Laptop). When a student logs in on a new device beyond this limit, their oldest active device is automatically signed out.'),
                    ]),

                Section::make('Part 2: Browser Tab Limits (Same-Device Tabs)')
                    ->description('Control how many simultaneous browser tabs a student can open the LMS in (e.g. 1 or 2 tabs). Excess tabs are blocked with a clear user notice.')
                    ->icon('heroicon-o-window')
                    ->components([
                        Toggle::make('tab_limit_enabled')
                            ->label('Enforce Simultaneous Browser Tab Limit')
                            ->helperText('When enabled, students opening more than the configured number of tabs will see a blocking message. When disabled, tabs are unlimited.')
                            ->default(true)
                            ->live(),

                        TextInput::make('student_max_tabs')
                            ->label('Maximum Concurrent Tabs Allowed Per Student')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->default(2)
                            ->suffix('browser tab(s)')
                            ->required()
                            ->visible(fn ($get) => (bool) $get('tab_limit_enabled'))
                            ->helperText('Recommended: 2 tabs (or 1 tab for strict exam mode). If set to 2 and a student opens 3 or more tabs, the extra tab will immediately be blocked and show an alert instructing them to close other tabs.'),
                    ]),

                Section::make('Administrator Panel Security')
                    ->description('Set inactivity logout policy for administrators and staff logged into the management portal.')
                    ->icon('heroicon-o-shield-check')
                    ->components([
                        TextInput::make('admin_session_lifetime_minutes')
                            ->label('Admin Session Lifetime')
                            ->numeric()
                            ->minValue(15)
                            ->maxValue(1440)
                            ->default(120)
                            ->suffix('minutes')
                            ->required()
                            ->helperText('Minutes of inactivity before an administrator is automatically logged out (e.g. 120 for 2 hours, 480 for 8 hours).'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $deviceLimitEnabled = (bool) ($state['device_limit_enabled'] ?? true);
        $studentMaxDevices = (int) ($state['student_max_devices'] ?? 2);
        $tabLimitEnabled = (bool) ($state['tab_limit_enabled'] ?? true);
        $studentMaxTabs = (int) ($state['student_max_tabs'] ?? 2);
        $adminSessionLifetime = (int) ($state['admin_session_lifetime_minutes'] ?? 120);

        $oldDeviceEnabled = filter_var(Setting::get('device_limit_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $oldDeviceLimit = (int) Setting::get('student_max_devices', config('lms.max_devices', 2) ?: 2);
        $oldTabEnabled = filter_var(Setting::get('tab_limit_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $oldTabLimit = (int) Setting::get('student_max_tabs', 2);
        $oldAdminLifetime = (int) Setting::get('admin_session_lifetime_minutes', 120);

        Setting::set('device_limit_enabled', $deviceLimitEnabled ? '1' : '0', 'Enable or disable student cross-device limit.');
        Setting::set('student_max_devices', $studentMaxDevices, 'Maximum concurrent logged-in devices per student.');
        Setting::set('tab_limit_enabled', $tabLimitEnabled ? '1' : '0', 'Enable or disable concurrent browser tab limit.');
        Setting::set('student_max_tabs', $studentMaxTabs, 'Maximum concurrent open browser tabs per student.');
        Setting::set('admin_session_lifetime_minutes', $adminSessionLifetime, 'Admin session timeout in minutes.');

        ActivityLog::record(
            event: 'admin.session_limit_updated',
            description: sprintf(
                'Super Admin updated session policy: Devices [%s, max %d], Tabs [%s, max %d], Admin timeout [%d min].',
                $deviceLimitEnabled ? 'Enabled' : 'Disabled',
                $studentMaxDevices,
                $tabLimitEnabled ? 'Enabled' : 'Disabled',
                $studentMaxTabs,
                $adminSessionLifetime
            ),
            subject: null,
            properties: [
                'device_limit' => [
                    'enabled' => $deviceLimitEnabled,
                    'max' => $studentMaxDevices,
                    'old_enabled' => $oldDeviceEnabled,
                    'old_max' => $oldDeviceLimit,
                ],
                'tab_limit' => [
                    'enabled' => $tabLimitEnabled,
                    'max' => $studentMaxTabs,
                    'old_enabled' => $oldTabEnabled,
                    'old_max' => $oldTabLimit,
                ],
                'admin_session_lifetime_minutes' => [
                    'old' => $oldAdminLifetime,
                    'new' => $adminSessionLifetime,
                ],
            ]
        );

        Notification::make()
            ->title('Session Limits & Policies Saved')
            ->body(sprintf(
                'Device Limit: %s (%d max) | Tab Limit: %s (%d max).',
                $deviceLimitEnabled ? 'Active' : 'Disabled',
                $studentMaxDevices,
                $tabLimitEnabled ? 'Active' : 'Disabled',
                $studentMaxTabs
            ))
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revokeAllStudents')
                ->label('Revoke All Student Sessions Everywhere')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revoke Every Student Active Session?')
                ->modalDescription('This will immediately log out every student across all devices and phones. They will need to verify OTP to sign in again. Use this if you suspect credentials sharing or during security audits.')
                ->action(function () {
                    $tokensCount = DB::table('personal_access_tokens')->count();
                    DB::table('personal_access_tokens')->delete();

                    ActivityLog::record(
                        event: 'admin.all_sessions_revoked',
                        description: "Super Admin revoked all active student sessions ({$tokensCount} tokens cleared).",
                        subject: null,
                        properties: ['tokens_revoked' => $tokensCount]
                    );

                    Notification::make()
                        ->title('All Student Sessions Revoked')
                        ->body("Terminated {$tokensCount} active session tokens.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
