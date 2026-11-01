<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\WordPress\WordPressLink;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/** Super Admin: the secret that lets the WordPress shop talk to the LMS (payments, student sign-in links). */
class WordPressLinkSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|\UnitEnum|null $navigationGroup = 'Super Admin';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'WordPress link';

    protected static ?string $navigationLabel = 'WordPress link';

    protected string $view = 'filament.pages.wordpress-link-settings';

    /** Shown once, right after it was made. Never stored in plain text. */
    public ?string $newSecret = null;

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function status(): array
    {
        $set = filled(WordPressLink::secret());

        return [
            'set' => $set,
            'strong' => WordPressLink::secretIsStrong(),
            'source' => Setting::get('wordpress_secret') ? 'this panel' : ($set ? 'the server .env file' : null),
            'api_url' => rtrim(url('/api/v1'), '/'),
            'student_url' => rtrim((string) config('lms.student_app_url'), '/'),
        ];
    }

    public function generate(): void
    {
        abort_unless(static::canAccess(), 403);

        $secret = Str::random(48);
        Setting::set('wordpress_secret', Crypt::encryptString($secret), 'Shared secret with the WordPress shop (encrypted).');
        $this->newSecret = $secret;

        ActivityLog::record(
            event: 'wordpress.secret_created',
            description: 'Super Admin created a new WordPress link secret.',
            admin: auth('admin')->user()
        );

        Notification::make()->title('New secret created')->body('Copy it into the WordPress plugin now. It is shown only once.')->warning()->send();
    }

    public function dismiss(): void
    {
        $this->newSecret = null;
    }
}
