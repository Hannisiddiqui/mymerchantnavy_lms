<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Enrollment;
use App\Services\Purchases\RazorpayClient;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Super Admin: link the Razorpay account to the admin panel, see recent payments and spot ones that never gave a course.
 * (Taking payments stays in the WordPress plugin; coupons are under Sales > Coupons.)
 */
class RazorpaySettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Razorpay';

    protected static ?string $navigationLabel = 'Razorpay';

    protected string $view = 'filament.pages.razorpay-settings';

    public ?array $data = [];

    /** @var array<int,array<string,mixed>> */
    public array $payments = [];

    public ?string $paymentsError = null;

    public ?array $found = null;

    public ?string $foundError = null;

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $client = app(RazorpayClient::class);
        $this->form->fill(['key_id' => $client->keyId(), 'key_secret' => '', 'lookup_id' => '']);
        if ($client->configured()) {
            $this->refreshPayments();
        }
    }

    public function connected(): bool
    {
        return app(RazorpayClient::class)->configured();
    }

    public function mode(): string
    {
        return app(RazorpayClient::class)->mode();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('API keys')
                    ->description('Razorpay dashboard > Account & Settings > API Keys. Use the same keys as in WordPress. The secret is stored encrypted and never shown again.')
                    ->schema([
                        TextInput::make('key_id')->label('Key ID')->placeholder('rzp_live_…')->required(),
                        TextInput::make('key_secret')->label('Key Secret')->password()->revealable()
                            ->placeholder('Leave empty to keep the saved secret')
                            ->helperText('Only needed when you add or change the keys.'),
                    ])->columns(2),
                Section::make('Find a payment')
                    ->description('Paste a payment ID (pay_…) or order ID (order_…) from a student or from the purchase email to see exactly what Razorpay recorded.')
                    ->schema([
                        TextInput::make('lookup_id')->label('Payment or order ID')->placeholder('pay_XXXXXXXXXXXXXX'),
                    ]),
            ]);
    }

    public function saveKeys(): void
    {
        $d = $this->form->getState();
        RazorpayClient::saveKeys($d['key_id'], $d['key_secret'] ?? null);
        $this->data['key_secret'] = '';

        ActivityLog::record(
            event: 'razorpay.keys_updated',
            description: 'Super Admin updated the Razorpay keys used for payment lookups.',
            properties: ['key_id' => $d['key_id']],
            admin: auth('admin')->user()
        );

        $test = app(RazorpayClient::class)->test();
        $test['ok']
            ? Notification::make()->title('Razorpay connected')->body('The keys work ('.app(RazorpayClient::class)->mode().' mode).')->success()->send()
            : Notification::make()->title('Keys saved, but Razorpay refused them')->body((string) $test['error'])->danger()->send();

        $this->refreshPayments();
    }

    public function refreshPayments(): void
    {
        $res = app(RazorpayClient::class)->recentPayments(25);
        $this->paymentsError = $res['error'];
        $items = $res['data']['items'] ?? [];

        $given = Enrollment::whereIn('payment_id', array_column($items, 'id') ?: [''])->pluck('payment_id')->all();

        $this->payments = array_map(fn (array $p) => [
            'id' => $p['id'],
            'order_id' => $p['order_id'] ?? null,
            'status' => $p['status'] ?? '',
            'amount' => ((int) ($p['amount'] ?? 0)) / 100,
            'email' => $p['email'] ?? '',
            'contact' => $p['contact'] ?? '',
            'method' => $p['method'] ?? '',
            'created' => isset($p['created_at']) ? date('d M Y, h:i A', (int) $p['created_at']) : '',
            // captured money with no course given = the case to look at
            'course_given' => in_array($p['id'], $given, true),
        ], $items);
    }

    public function lookup(): void
    {
        $this->found = null;
        $this->foundError = null;
        $id = trim((string) ($this->data['lookup_id'] ?? ''));
        if ($id === '') {
            $this->foundError = 'Paste a payment or order ID first.';

            return;
        }
        $res = app(RazorpayClient::class)->lookup($id);
        if (! $res['ok']) {
            $this->foundError = $res['error'];

            return;
        }
        $d = $res['data'];
        $paymentIds = isset($d['_payments']) ? array_column($d['_payments'], 'id') : [$d['id'] ?? ''];
        $this->found = [
            'id' => $d['id'] ?? $id,
            'status' => $d['status'] ?? '',
            'amount' => ((int) ($d['amount'] ?? 0)) / 100,
            'email' => $d['email'] ?? ($d['notes']['email'] ?? ''),
            'contact' => $d['contact'] ?? '',
            'method' => $d['method'] ?? '',
            'order_id' => $d['order_id'] ?? ($d['id'] ?? ''),
            'error' => $d['error_description'] ?? null,
            'course_given' => Enrollment::whereIn('payment_id', array_filter($paymentIds))->exists(),
        ];
    }
}
