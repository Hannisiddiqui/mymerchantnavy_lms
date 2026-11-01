<?php

namespace App\Providers\Filament;

use App\Models\Setting;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Admin panel. In production it runs on admin.mymerchantnavy.com (set ADMIN_DOMAIN in .env);
 * locally it is at /admin.
 * Resources (one per thing an admin manages) live in app/Filament/Resources.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('lms.admin_domain') ? '' : 'admin')
            ->domain(config('lms.admin_domain'))
            ->authGuard('admin')
            ->login()
            ->profile()
            // Two-step verification (authenticator app + recovery codes). Every admin can switch it on after logging in
            // (profile page, top right). It only becomes compulsory if the Super Admin turns on "Require two-step" under
            // Super Admin > Security; the Super Admin's own account is never forced into it.
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: fn (): bool => ! (auth('admin')->user()?->isSuperAdmin() ?? false)
                    && filter_var(Setting::get('admin_2fa_required', '0'), FILTER_VALIDATE_BOOLEAN),
            )
            ->brandName('My Merchant Navy')
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->darkMode(true)
            ->colors([
                // Same blue as the student site
                'primary' => [
                    50 => '#eff6ff', 100 => '#dbeafe', 200 => '#bfdbfe', 300 => '#93c5fd', 400 => '#60a5fa',
                    500 => '#3b82f6', 600 => '#2563eb', 700 => '#1d4ed8', 800 => '#1e40af', 900 => '#1e3a8a', 950 => '#172554',
                ],
                'gray' => Color::Slate,
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.admin-head'))
            ->navigationGroups(['Learning', 'People', 'Sales', 'Reports', 'Settings', 'Super Admin'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class, \App\Http\Middleware\RestrictPreviewAdmin::class]);
    }
}
