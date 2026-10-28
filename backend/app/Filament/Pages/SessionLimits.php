<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    public int $student_max_devices = 0;

    public int $admin_session_lifetime_minutes = 120;

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->student_max_devices = (int) Setting::get('student_max_devices', config('lms.max_devices', 0));
        $this->admin_session_lifetime_minutes = (int) Setting::get('admin_session_lifetime_minutes', 120);
    }

    public function save(): void
    {
        $this->validate([
            'student_max_devices' => ['required', 'integer', 'min:0', 'max:10'],
            'admin_session_lifetime_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
        ]);

        $oldLimit = (int) Setting::get('student_max_devices', config('lms.max_devices', 0));
        $newLimit = $this->student_max_devices;

        $oldAdminLifetime = (int) Setting::get('admin_session_lifetime_minutes', 120);
        $newAdminLifetime = $this->admin_session_lifetime_minutes;

        Setting::set('student_max_devices', $newLimit, 'Maximum concurrent logged-in devices per student.');
        Setting::set('admin_session_lifetime_minutes', $newAdminLifetime, 'Admin session timeout in minutes.');

        ActivityLog::record(
            event: 'admin.session_limit_updated',
            description: "Super Admin updated student session limit from {$oldLimit} to {$newLimit} device(s) and admin session timeout to {$newAdminLifetime} mins.",
            subject: null,
            properties: [
                'student_max_devices' => [
                    'old' => $oldLimit,
                    'new' => $newLimit,
                ],
                'admin_session_lifetime_minutes' => [
                    'old' => $oldAdminLifetime,
                    'new' => $newAdminLifetime,
                ],
            ]
        );

        Notification::make()
            ->title('Session Limits Saved')
            ->body("Student session limit updated to {$newLimit} device(s).")
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
