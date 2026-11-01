<?php

namespace App\Services\Purchases;

use App\Models\Coupon;
use App\Models\Enrollment;
use App\Models\User;

/** Works out a coupon's discount. WordPress sends the course price (it owns the price list) and gets the discount back. */
class CouponService
{
    /**
     * @return array{valid: bool, code: ?string, discount: float, final: float, message: string}
     */
    public function quote(string $productId, float $price, ?string $code, ?string $email = null): array
    {
        $none = fn (string $msg, bool $valid = false) => ['valid' => $valid, 'code' => null, 'discount' => 0.0, 'final' => round($price, 2), 'message' => $msg];

        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return $none('');
        }
        if ($price <= 0) {
            return $none('This course is free, so no coupon is needed.');
        }

        $coupon = Coupon::where('code', $code)->first();
        if (! $coupon) {
            return $none('This coupon code is not valid.');
        }
        $status = $coupon->statusLabel();
        if ($status !== 'Live') {
            return $none(match ($status) {
                'Expired' => 'This coupon has expired.',
                'Scheduled' => 'This coupon is not active yet.',
                'Used up' => 'This coupon has been fully used.',
                default => 'This coupon is not active.',
            });
        }
        $products = array_map('trim', (array) $coupon->product_ids);
        if ($products && ! in_array(trim($productId), $products, true)) {
            return $none('This coupon does not work on this course.');
        }
        if ($coupon->min_amount && $price < (float) $coupon->min_amount) {
            return $none('This coupon needs a course price of at least ₹'.number_format((float) $coupon->min_amount, 0).'.');
        }
        if ($email && $coupon->per_student_limit) {
            $student = User::whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
            $used = $student ? Enrollment::where('user_id', $student->id)->where('coupon_code', $coupon->code)->count() : 0;
            if ($used >= $coupon->per_student_limit) {
                return $none('You have already used this coupon.');
            }
        }

        $discount = $coupon->type === 'fixed'
            ? (float) $coupon->value
            : $price * min((float) $coupon->value, 100) / 100;
        if ($coupon->max_discount) {
            $discount = min($discount, (float) $coupon->max_discount);
        }
        // Razorpay cannot charge less than ₹1: the discount never takes the price below that.
        $discount = round(max(0, min($discount, $price - 1)), 2);

        return [
            'valid' => true,
            'code' => $coupon->code,
            'discount' => $discount,
            'final' => round($price - $discount, 2),
            'message' => 'Coupon applied: you save ₹'.number_format($discount, 2).'.',
        ];
    }

    public function markUsed(?string $code): void
    {
        if ($code) {
            Coupon::where('code', strtoupper($code))->increment('used_count');
        }
    }
}
