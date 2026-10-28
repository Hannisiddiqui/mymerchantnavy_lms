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
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Super-Admin-only page to configure session limits and security policies.
 */
class SessionLimits extends Page implements HasSchemas
{
    use InteractsWithSchemas;
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
                    ->schema([
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
                            ->suffix('device(s)')
                            ->default(2)
                            ->required()
                            ->visible(fn ($get) => (bool) $get('device_limit_enabled'))
                            ->helperText('Recommended: 2 devices (Phone + Laptop). When a student logs in on a new device beyond this limit, their oldest active device is automatically signed out.'),
                    ]),

                Section::make('Part 2: Browser Tab Limits (Same-Device Tabs)')
                    ->description('Control how many simultaneous browser tabs a student can open the LMS in (e.g. 1 or 2 tabs). Excess tabs are blocked with a clear user notice.')
                    ->icon('heroicon-o-window')
                    ->schema([
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
                            ->suffix('browser tab(s)')
                            ->default(2)
                            ->required()
                            ->visible(fn ($get) => (bool) $get('tab_limit_enabled'))
                            ->helperText('Recommended: 2 tabs (or 1 tab for strict exam mode). If set to 2 and a student opens 3 or more tabs, the extra tab will immediately be blocked and show an alert instructing them to close other tabs.'),
                    ]),

                Section::make('Administrator Panel Security')
                    ->description('Set inactivity logout policy for administrators and staff logged into the management portal.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        TextInput::make('admin_session_lifetime_minutes')
                            ->label('Admin Session Lifetime')
                            ->numeric()
                            ->minValue(15)
                            ->maxValue(1440)
                            ->suffix('minutes')
                            ->default(120)
                            ->required()
                            ->helperText('Minutes of inactivity before an administrator is automatically logged out (e.g. 120 for 2 hours, 480 for 8 hours).'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $deviceEnabled = (bool) ($state['device_limit_enabled'] ?? true);
        $maxDevices = $deviceEnabled ? max(1, (int) ($state['student_max_devices'] ?? 2)) : 0;

        $tabEnabled = (bool) ($state['tab_limit_enabled'] ?? true);
        $maxTabs = $tabEnabled ? max(1, (int) ($state['student_max_tabs'] ?? 2)) : 0;

        $adminLifetime = max(15, (int) ($state['admin_session_lifetime_minutes'] ?? 120));

        Setting::set('device_limit_enabled', $deviceEnabled ? '1' : '0', 'Enable or disable student physical device limit enforcement.');
        Setting::set('student_max_devices', $maxDevices, 'Maximum concurrent logged-in devices per student (0 = unlimited).');

        Setting::set('tab_limit_enabled', $tabEnabled ? '1' : '0', 'Enable or disable simultaneous browser tab limit enforcement.');
        Setting::set('student_max_tabs', $maxTabs, 'Maximum concurrent active browser tabs allowed per student (0 = unlimited).');

        Setting::set('admin_session_lifetime_minutes', $adminLifetime, 'Admin session timeout in minutes.');

        ActivityLog::record(
            event: 'admin.session_limit_updated',
            description: sprintf(
                'Super Admin updated session policy: Devices [%s, max %d], Tabs [%s, max %d], Admin timeout [%d min].',
                $deviceEnabled ? 'Enabled' : 'Disabled',
                $maxDevices,
                $tabEnabled ? 'Enabled' : 'Disabled',
                $maxTabs,
                $adminLifetime
            ),
            properties: [
                'device_limit_enabled' => $deviceEnabled,
                'student_max_devices' => $maxDevices,
                'tab_limit_enabled' => $tabEnabled,
                'student_max_tabs' => $maxTabs,
                'admin_session_lifetime_minutes' => $adminLifetime,
            ],
            admin: auth('admin')->user()
        );

        Notification::make()
            ->title('Session policies saved successfully')
            ->body('Device limits, tab limits, and administrator timeout policies have been updated.')
            ->success()
            ->send();
    }

    public function revokeAllStudentSessions(): void
    {
        $count = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'user')
            ->count();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'user')
            ->delete();

        ActivityLog::record(
            event: 'security.revoke_all_sessions',
            description: "Super Admin executed emergency revocation of all {$count} active student sessions across all devices.",
            properties: ['revoked_tokens_count' => $count],
            admin: auth('admin')->user()
        );

        Notification::make()
            ->title('All student sessions revoked')
            ->body("Revoked {$count} active device session token(s). All students must log in again with OTP.")
            ->warning()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revokeAll')
                ->label('Revoke All Student Sessions Everywhere')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revoke all student sessions immediately?')
                ->modalDescription('This will forcefully log out every student from all devices and browser tabs right now. They must sign in again.')
                ->modalSubmitActionLabel('Revoke Everywhere Now')
                ->action(fn () => $this->revokeAllStudentSessions()),
        ];
    }
}
