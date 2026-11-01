<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Setting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Super Admin: two-step verification rules and a live security checklist of the server settings. */
class SecuritySettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static string|\UnitEnum|null $navigationGroup = 'Super Admin';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Security';

    protected static ?string $navigationLabel = 'Security';

    protected string $view = 'filament.pages.security-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'admin_2fa_required' => filter_var(Setting::get('admin_2fa_required', '0'), FILTER_VALIDATE_BOOLEAN),
            'student_2fa_mode' => \App\Services\Auth\StudentTwoFactor::mode(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Two-step verification')
                ->description('Admins switch it on from the top-right menu > Profile (you can force it below). Students are controlled by the setting below.')
                ->schema([
                    Toggle::make('admin_2fa_required')
                        ->label('Force two-step verification for every admin (except you)')
                        ->helperText('When ON, each admin is made to set up an authenticator app at their next login. Leave OFF to let admins choose. Your own Super Admin account is never forced, but we strongly recommend turning it on for yourself.'),
                    Select::make('student_2fa_mode')
                        ->label('Two-step verification for students')
                        ->options([
                            'required' => 'Compulsory: every student must set it up (recommended)',
                            'optional' => 'Optional: students choose under My Profile',
                            'off' => 'Off: students are never asked',
                        ])
                        ->native(false)->selectablePlaceholder(false)
                        ->helperText('Compulsory: after their emailed login code, a student who has not set it up is taken to a set-up screen and cannot open anything else until it is done. They cannot turn it off themselves. Lost phone: Students > Reset two-step.'),
                ])->columns(1),
        ]);
    }

    public function save(): void
    {
        $d = $this->form->getState();
        Setting::set('admin_2fa_required', $d['admin_2fa_required'] ? '1' : '0', 'Require two-step verification for every admin.');
        Setting::set('student_2fa_mode', $d['student_2fa_mode'], 'Students and two-step verification: required, optional or off.');

        ActivityLog::record(
            event: 'security.settings_updated',
            description: 'Super Admin updated the two-step verification rules.',
            properties: ['admin_2fa_required' => (bool) $d['admin_2fa_required'], 'student_2fa_mode' => $d['student_2fa_mode']],
            admin: auth('admin')->user()
        );

        Notification::make()->title('Security settings saved')->success()->send();
    }

    /** @return list<array{ok: bool, title: string, hint: string}> */
    public function checks(): array
    {
        $url = (string) config('app.url');
        $super = Admin::where('email', Admin::SUPER_ADMIN_EMAIL)->first();

        return [
            ['ok' => ! config('app.debug'), 'title' => 'Debug mode is off', 'hint' => 'Set APP_DEBUG=false in the server .env. Debug mode shows passwords and keys in error pages.'],
            ['ok' => config('app.env') === 'production', 'title' => 'Environment is "production"', 'hint' => 'Set APP_ENV=production in the server .env.'],
            ['ok' => str_starts_with($url, 'https://'), 'title' => 'The site address uses HTTPS', 'hint' => 'Set APP_URL=https://api.mymerchantnavy.com in the server .env.'],
            ['ok' => (bool) config('session.secure'), 'title' => 'Login cookies are HTTPS-only', 'hint' => 'Set SESSION_SECURE_COOKIE=true in the server .env.'],
            ['ok' => (bool) config('session.http_only'), 'title' => 'Login cookies are hidden from scripts', 'hint' => 'Set SESSION_HTTP_ONLY=true.'],
            ['ok' => in_array(config('session.same_site'), ['lax', 'strict'], true), 'title' => 'Login cookies are same-site only', 'hint' => 'Set SESSION_SAME_SITE=lax (or strict).'],
            ['ok' => (bool) config('session.encrypt'), 'title' => 'Sessions are encrypted', 'hint' => 'Set SESSION_ENCRYPT=true.'],
            ['ok' => config('queue.default') !== 'sync' || config('app.env') !== 'production', 'title' => 'Emails go through the queue', 'hint' => 'Use QUEUE_CONNECTION=database in production.'],
            ['ok' => (bool) $super?->hasTwoFactor(), 'title' => 'Your Super Admin account has two-step verification (recommended)', 'hint' => 'Top-right menu > Profile > Two-factor authentication > Set up.'],
            ['ok' => filled(config('lms.wordpress.secret')) && strlen((string) config('lms.wordpress.secret')) >= 32, 'title' => 'The WordPress link secret is long and random', 'hint' => 'Run tools/set-wp-secret.sh on the server.'],
        ];
    }

    /** @return \Illuminate\Support\Collection<int,Admin> */
    public function admins()
    {
        return Admin::orderBy('name')->get();
    }
}
