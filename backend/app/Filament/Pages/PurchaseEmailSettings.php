<?php

namespace App\Filament\Pages;

use App\Mail\PurchaseMail;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\Purchases\PurchaseEmailTemplates as T;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Mail;

/**
 * Super Admin: who receives the "student purchased a course" e-mail, and the wording of both purchase e-mails
 * (the confirmation to the student and the alert to the admins).
 */
class PurchaseEmailSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 12;

    protected static ?string $title = 'Purchase Emails';

    protected static ?string $navigationLabel = 'Purchase Emails';

    protected string $view = 'filament.pages.purchase-email-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->currentValues());
    }

    private function currentValues(): array
    {
        return [
            'student_enabled' => T::enabled('student'),
            'student_subject' => T::get('student_subject'),
            'student_body' => T::get('student_body'),
            'admin_enabled' => T::enabled('admin'),
            'admin_recipients' => T::get('admin_recipients'),
            'admin_subject' => T::get('admin_subject'),
            'admin_body' => T::get('admin_body'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Email to the admin team')
                    ->description('Sent every time a student buys a course. It carries the student and payment details so you can check any payment problem quickly.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Toggle::make('admin_enabled')->label('Send this email')->default(true)->columnSpanFull(),
                        TextInput::make('admin_recipients')
                            ->label('Send to (one or more email addresses)')
                            ->helperText('Separate several addresses with commas.')
                            ->required()
                            ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                                $list = preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                                foreach ($list as $address) {
                                    if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                                        $fail("“{$address}” is not a valid email address.");
                                    }
                                }
                            }])
                            ->columnSpanFull(),
                        TextInput::make('admin_subject')->label('Subject')->required()->live(onBlur: true)->columnSpanFull(),
                        Textarea::make('admin_body')->label('Message')->rows(9)->required()->live(onBlur: true)->columnSpanFull()
                            ->helperText('Plain text. Use the placeholders listed on the right; {details_table} adds the full details table.'),
                    ]),

                Section::make('Email to the student')
                    ->description('The confirmation the student receives after paying: course, validity and the link to start learning.')
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        Toggle::make('student_enabled')->label('Send this email')->default(true)->columnSpanFull(),
                        TextInput::make('student_subject')->label('Subject')->required()->live(onBlur: true)->columnSpanFull(),
                        Textarea::make('student_body')->label('Message')->rows(9)->required()->live(onBlur: true)->columnSpanFull()
                            ->helperText('Plain text. {access_button} adds the “Start learning” button.'),
                    ]),
            ]);
    }

    /** @return array<string,string> */
    public function placeholders(): array
    {
        return T::placeholders();
    }

    /** HTML preview of one of the two emails with sample data (uses what is typed, even before saving). */
    public function preview(string $who): array
    {
        $values = T::sampleValues();

        return [
            'subject' => T::subject((string) ($this->data[$who.'_subject'] ?? ''), $values),
            'html' => T::bodyHtml((string) ($this->data[$who.'_body'] ?? ''), $values),
        ];
    }

    public function save(): void
    {
        $d = $this->form->getState();
        $keys = T::KEYS;

        Setting::set($keys['admin_enabled'], $d['admin_enabled'] ? '1' : '0', 'Send the purchase alert email to admins.');
        Setting::set($keys['admin_recipients'], implode(', ', preg_split('/[\s,;]+/', trim($d['admin_recipients']), -1, PREG_SPLIT_NO_EMPTY)), 'Admin email addresses that receive purchase alerts.');
        Setting::set($keys['admin_subject'], $d['admin_subject'], 'Subject of the purchase alert email.');
        Setting::set($keys['admin_body'], $d['admin_body'], 'Body of the purchase alert email.');
        Setting::set($keys['student_enabled'], $d['student_enabled'] ? '1' : '0', 'Send the purchase confirmation email to students.');
        Setting::set($keys['student_subject'], $d['student_subject'], 'Subject of the student purchase email.');
        Setting::set($keys['student_body'], $d['student_body'], 'Body of the student purchase email.');

        ActivityLog::record(
            event: 'purchase_emails.updated',
            description: 'Super Admin updated the purchase email settings.',
            properties: ['admin_recipients' => $d['admin_recipients'], 'admin_enabled' => (bool) $d['admin_enabled'], 'student_enabled' => (bool) $d['student_enabled']],
            admin: auth('admin')->user()
        );

        Notification::make()->title('Purchase emails saved')->body('New purchases will use this wording and these recipients.')->success()->send();
    }

    public function resetToDefault(): void
    {
        foreach (T::defaults() as $key => $value) {
            Setting::set($key, $value);
        }
        $this->form->fill($this->currentValues());

        Notification::make()->title('Default wording restored')->success()->send();
    }

    /** Sends both emails, filled with sample data, to the logged-in admin so they can see the real thing. */
    public function sendTest(): void
    {
        $to = auth('admin')->user()?->email;
        $values = T::sampleValues();

        try {
            foreach (['admin' => 'New course purchase', 'student' => 'Your purchase is confirmed'] as $who => $heading) {
                Mail::to($to)->sendNow(new PurchaseMail(
                    '[TEST] '.T::subject((string) ($this->data[$who.'_subject'] ?? ''), $values),
                    T::bodyHtml((string) ($this->data[$who.'_body'] ?? ''), $values),
                    $heading,
                ));
            }
            Notification::make()->title('Test emails sent')->body("Two sample emails were sent to {$to}.")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Could not send the test email')->body($e->getMessage())->danger()->send();
        }
    }
}
