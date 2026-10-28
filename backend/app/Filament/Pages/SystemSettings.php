<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Super Admin & Admin System and Notification Settings.
 * Configure change alert emails, support routing, and WhatsApp numbers.
 */
class SystemSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'System & Notification Settings';

    protected static ?string $navigationLabel = 'Notifications & Support';

    protected string $view = 'filament.pages.system-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'audit_notifications_enabled' => filter_var(Setting::get('audit_notifications_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'audit_notification_emails' => Setting::get('audit_notification_emails', 'support.mymerchantnavy@gmail.com'),
            'support_email' => Setting::get('support_email', 'support.mymerchantnavy@gmail.com'),
            'support_whatsapp_number' => Setting::get('support_whatsapp_number', '919911223344'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Audit & System Change Email Alerts')
                    ->description('Automatically receive email notifications with the actor name, email, IP address, and changed details whenever a change is made.')
                    ->schema([
                        Toggle::make('audit_notifications_enabled')
                            ->label('Enable Email Notifications on Website & LMS Changes')
                            ->helperText('When enabled, emails are dispatched immediately on course updates, session limit changes, student removals, etc.')
                            ->default(true),

                        TextInput::make('audit_notification_emails')
                            ->label('Alert Recipient Email Address(es)')
                            ->helperText('Multiple emails may be separated with commas (e.g. support.mymerchantnavy@gmail.com, admin@mymerchantnavy.com).')
                            ->required(),
                    ]),

                Section::make('Student Support & Query Routing')
                    ->description('Configure where student chatbot queries, doubts, and WhatsApp requests are delivered.')
                    ->schema([
                        TextInput::make('support_email')
                            ->label('Support Email Address')
                            ->helperText('Student doubts and inquiries submitted via the in-app chatbot will be delivered here.')
                            ->email()
                            ->required(),

                        TextInput::make('support_whatsapp_number')
                            ->label('Support WhatsApp Number')
                            ->helperText('International number without spaces or + (e.g. 919911223344). Powers the "Chat on WhatsApp" button in the student app.')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public function save(): void
    {
        $formData = $this->form->getState();

        Setting::set('audit_notifications_enabled', $formData['audit_notifications_enabled'] ? '1' : '0', 'Enable or disable change notification emails.');
        Setting::set('audit_notification_emails', $formData['audit_notification_emails'], 'Recipient emails for audit change alerts.');
        Setting::set('support_email', $formData['support_email'], 'Support email address for student queries.');
        Setting::set('support_whatsapp_number', $formData['support_whatsapp_number'], 'WhatsApp support number for direct student chats.');

        ActivityLog::record(
            event: 'system.settings_updated',
            description: 'Super Admin updated System & Notification Settings.',
            properties: [
                'audit_notifications_enabled' => (bool) $formData['audit_notifications_enabled'],
                'audit_notification_emails' => $formData['audit_notification_emails'],
                'support_email' => $formData['support_email'],
                'support_whatsapp_number' => $formData['support_whatsapp_number'],
            ],
            admin: auth('admin')->user()
        );

        Notification::make()
            ->title('Settings Saved Successfully')
            ->body('System notification rules and support contact channels have been updated.')
            ->success()
            ->send();
    }
}
