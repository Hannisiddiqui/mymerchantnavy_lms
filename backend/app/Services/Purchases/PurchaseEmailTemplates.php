<?php

namespace App\Services\Purchases;

use App\Models\Admin;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The two purchase emails (to the student, to the admins): default wording, placeholders, and rendering.
 * The Super Admin edits subject and body on Settings > Purchase Emails; they are stored in system_settings.
 */
class PurchaseEmailTemplates
{
    public const KEYS = [
        'student_enabled' => 'purchase_email_student_enabled',
        'student_subject' => 'purchase_email_student_subject',
        'student_body' => 'purchase_email_student_body',
        'admin_enabled' => 'purchase_email_admin_enabled',
        'admin_recipients' => 'purchase_email_admin_recipients',
        'admin_subject' => 'purchase_email_admin_subject',
        'admin_body' => 'purchase_email_admin_body',
    ];

    /** @return array<string,string> placeholder => what it becomes */
    public static function placeholders(): array
    {
        return [
            '{student_name}' => 'Student name',
            '{student_email}' => 'Student email',
            '{student_phone}' => 'Student mobile number',
            '{course_names}' => 'Course name(s), comma separated',
            '{validity}' => 'Validity of each course (e.g. valid until 12 May 2027, or Lifetime)',
            '{amount}' => 'Amount paid (e.g. ₹4,999.00)',
            '{payment_status}' => 'Paid / Free / Payment ID missing',
            '{order_id}' => 'Order ID',
            '{payment_id}' => 'Razorpay payment ID',
            '{purchase_date}' => 'Date and time of purchase',
            '{coupon}' => 'Coupon used (or "None")',
            '{access_link}' => 'The course link as plain text',
            '{access_button}' => 'A "Start learning" button',
            '{details_table}' => 'A neat table with all purchase details (recommended in the admin email)',
            '{support_email}' => 'Support email address',
        ];
    }

    public static function defaults(): array
    {
        return [
            self::KEYS['student_enabled'] => '1',
            self::KEYS['student_subject'] => 'Your purchase is confirmed: {course_names}',
            self::KEYS['student_body'] => "Hi {student_name},\n\nThank you for your purchase! Your course is ready and you can start learning right away.\n\n{details_table}\n\n{access_button}\n\nNeed help? Write to us at {support_email}.\n\nTeam My Merchant Navy",
            self::KEYS['admin_enabled'] => '1',
            self::KEYS['admin_recipients'] => Admin::SUPER_ADMIN_EMAIL,
            self::KEYS['admin_subject'] => 'New purchase: {student_name} bought {course_names} ({amount})',
            self::KEYS['admin_body'] => "A student has just purchased a course.\n\n{details_table}\n\nIf this student reports a problem (payment stuck, no access), search for the payment ID in Razorpay and check Sales > Purchases in the admin panel.",
        ];
    }

    public static function get(string $name): string
    {
        $key = self::KEYS[$name];

        return (string) Setting::get($key, self::defaults()[$key]);
    }

    public static function enabled(string $who): bool
    {
        return filter_var(self::get($who.'_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return list<string> valid addresses from the comma / semicolon / space separated setting */
    public static function adminRecipients(): array
    {
        $list = preg_split('/[\s,;]+/', self::get('admin_recipients'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($list, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }

    // ------------------------------------------------------------------ values

    /**
     * @param  Collection<int,Enrollment>  $enrollments  everything one payment gave (usually one course)
     * @return array<string,string> placeholder => plain text value (HTML blocks are added in render())
     */
    public static function values(User $student, Collection $enrollments): array
    {
        $enrollments->loadMissing('course');
        $first = $enrollments->first();

        $names = $enrollments->map(fn (Enrollment $e) => $e->course?->title ?? 'Course')->implode(', ');
        $validity = $enrollments->map(function (Enrollment $e) use ($enrollments) {
            $v = $e->expires_at ? 'valid until '.$e->expires_at->timezone(config('app.timezone'))->format('d F Y') : 'Lifetime access';

            return $enrollments->count() > 1 ? ($e->course?->title.': '.$v) : $v;
        })->implode('; ');

        // every enrollment of one payment carries the whole order amount, so take it once
        $amountValue = (float) ($enrollments->max(fn (Enrollment $e) => (float) $e->amount) ?? 0);
        $paymentId = $first?->payment_id;
        $status = match (true) {
            $first?->purchase_type === 'free' => 'Free enrolment (no payment needed)',
            filled($paymentId) => 'Paid',
            default => 'Payment ID missing: please verify in Razorpay',
        };

        $entry = $first;
        $link = rtrim((string) config('lms.student_app_url'), '/').'/my-courses'.($enrollments->count() === 1 ? '/'.$first?->course_id : '');

        return [
            '{student_name}' => $student->name ?: ($student->nickname ?: 'there'),
            '{student_email}' => (string) $student->email,
            '{student_phone}' => $student->phone ?: 'Not given',
            '{course_names}' => $names,
            '{validity}' => $validity,
            '{amount}' => $first?->purchase_type === 'free' ? 'Free' : '₹'.number_format($amountValue, 2),
            '{payment_status}' => $status,
            '{order_id}' => $entry?->order_id ?: 'N/A',
            '{payment_id}' => $paymentId ?: 'N/A',
            '{purchase_date}' => ($entry?->purchase_date ?? now())->timezone(config('app.timezone'))->format('d M Y, h:i A'),
            '{coupon}' => $entry?->coupon_code ? $entry->coupon_code.' (− ₹'.number_format((float) $entry->discount_amount, 2).')' : 'None',
            '{access_link}' => $link,
            '{support_email}' => (string) Setting::get('support_email', Admin::SUPER_ADMIN_EMAIL),
        ];
    }

    /** Fake data for the preview and the test e-mail on the settings page. */
    public static function sampleValues(): array
    {
        return [
            '{student_name}' => 'Riya Shah', '{student_email}' => 'riya@example.com', '{student_phone}' => '9876543210',
            '{course_names}' => 'IMU-CET 2027 Complete Course', '{validity}' => 'valid until 12 May 2027',
            '{amount}' => '₹4,999.00', '{payment_status}' => 'Paid', '{order_id}' => 'order_SAMPLE12345',
            '{payment_id}' => 'pay_SAMPLE12345', '{purchase_date}' => now()->format('d M Y, h:i A'), '{coupon}' => 'WELCOME10 (− ₹500.00)',
            '{access_link}' => rtrim((string) config('lms.student_app_url'), '/').'/my-courses/1',
            '{support_email}' => (string) Setting::get('support_email', Admin::SUPER_ADMIN_EMAIL),
        ];
    }

    // ------------------------------------------------------------------ rendering

    public static function subject(string $template, array $values): string
    {
        return trim(strtr($template, array_map(fn ($v) => preg_replace('/\s+/', ' ', (string) $v), $values)));
    }

    /** Safe HTML for the body: the template and every value are escaped; only the table and button blocks are real HTML. */
    public static function bodyHtml(string $template, array $values): string
    {
        $html = e($template);
        $safe = array_map(fn ($v) => e((string) $v), $values);
        $html = strtr($html, $safe);

        $rows = [
            'Student' => $values['{student_name}'], 'Email' => $values['{student_email}'], 'Mobile' => $values['{student_phone}'],
            'Course' => $values['{course_names}'], 'Validity' => $values['{validity}'], 'Amount' => $values['{amount}'],
            'Payment status' => $values['{payment_status}'], 'Order ID' => $values['{order_id}'],
            'Payment ID' => $values['{payment_id}'], 'Purchased on' => $values['{purchase_date}'], 'Coupon' => $values['{coupon}'],
        ];
        $table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;border-collapse:separate;margin:6px 0">';
        foreach ($rows as $label => $value) {
            $table .= '<tr><td style="padding:9px 14px;border-bottom:1px solid #eef2f7;font-size:13px;color:#64748b;width:34%">'.e($label)
                .'</td><td style="padding:9px 14px;border-bottom:1px solid #eef2f7;font-size:14px;font-weight:600;color:#0f172a">'.e((string) $value).'</td></tr>';
        }
        $table .= '</table>';

        $button = '<p style="margin:14px 0"><a href="'.e($values['{access_link}']).'" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 26px;border-radius:10px">Start learning</a></p>';

        $html = nl2br(strtr($html, ['{details_table}' => '@@TABLE@@', '{access_button}' => '@@BUTTON@@']), false);

        return strtr($html, ['@@TABLE@@' => $table, '@@BUTTON@@' => $button]);
    }
}
