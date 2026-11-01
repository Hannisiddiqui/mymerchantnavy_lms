<?php

namespace App\Services\Purchases;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Read-only link to the Razorpay account, used by Sales > Razorpay in the admin panel to check payments.
 * The key pair is saved by the Super Admin (the secret encrypted); .env RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET work too.
 * Payments themselves are taken by the WordPress plugin; this class never charges or refunds anything.
 */
class RazorpayClient
{
    private const BASE = 'https://api.razorpay.com/v1';

    public function keyId(): string
    {
        return (string) (Setting::get('razorpay_key_id') ?: config('services.razorpay.key_id'));
    }

    private function secret(): string
    {
        $stored = Setting::get('razorpay_key_secret');
        if ($stored) {
            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable) {
                return '';
            }
        }

        return (string) config('services.razorpay.key_secret');
    }

    public function configured(): bool
    {
        return $this->keyId() !== '' && $this->secret() !== '';
    }

    public function mode(): string
    {
        return str_starts_with($this->keyId(), 'rzp_live_') ? 'Live' : 'Test';
    }

    public static function saveKeys(string $keyId, ?string $secret): void
    {
        Setting::set('razorpay_key_id', trim($keyId), 'Razorpay Key ID (admin lookups only).');
        if (filled($secret)) {
            Setting::set('razorpay_key_secret', Crypt::encryptString(trim($secret)), 'Razorpay Key Secret, encrypted.');
        }
    }

    /** @return array{ok: bool, data: array, error: ?string} */
    private function get(string $path, array $query = []): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'data' => [], 'error' => 'Razorpay keys are not saved yet.'];
        }

        try {
            $res = Http::withBasicAuth($this->keyId(), $this->secret())->acceptJson()->timeout(15)->get(self::BASE.$path, $query);
        } catch (\Throwable $e) {
            return ['ok' => false, 'data' => [], 'error' => 'Could not reach Razorpay: '.$e->getMessage()];
        }

        if (! $res->successful()) {
            return ['ok' => false, 'data' => [], 'error' => $res->json('error.description') ?: 'Razorpay answered with status '.$res->status().'.'];
        }

        return ['ok' => true, 'data' => $res->json() ?? [], 'error' => null];
    }

    public function test(): array
    {
        return $this->get('/payments', ['count' => 1]);
    }

    /** @return array{ok: bool, data: array, error: ?string} newest payments first */
    public function recentPayments(int $count = 25): array
    {
        return $this->get('/payments', ['count' => max(1, min($count, 100))]);
    }

    /** Looks up a payment (pay_...) or an order (order_...) by its id. */
    public function lookup(string $id): array
    {
        $id = trim($id);
        if (str_starts_with($id, 'order_')) {
            $order = $this->get('/orders/'.rawurlencode($id));
            if ($order['ok']) {
                $order['data']['_payments'] = $this->get('/orders/'.rawurlencode($id).'/payments')['data']['items'] ?? [];
            }

            return $order;
        }

        return $this->get('/payments/'.rawurlencode($id));
    }
}
