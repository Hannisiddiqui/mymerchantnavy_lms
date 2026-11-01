<?php

namespace App\Services\Purchases;

use App\Mail\PurchaseMail;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the two e-mails of a purchase: the confirmation to the student and the alert to the admin address(es).
 * Never throws: a mail problem must not undo a successful enrolment (the shop would retry and the student would be stuck).
 */
class PurchaseNotifier
{
    /** @param Collection<int,Enrollment> $enrollments everything one payment gave to this student */
    public function send(User $student, Collection $enrollments): void
    {
        if ($enrollments->isEmpty()) {
            return;
        }

        try {
            $values = PurchaseEmailTemplates::values($student, $enrollments);

            if (PurchaseEmailTemplates::enabled('student') && filled($student->email)) {
                Mail::to($student->email)->queue(new PurchaseMail(
                    PurchaseEmailTemplates::subject(PurchaseEmailTemplates::get('student_subject'), $values),
                    PurchaseEmailTemplates::bodyHtml(PurchaseEmailTemplates::get('student_body'), $values),
                    'Your purchase is confirmed',
                    $values['{support_email}'],
                ));
            }

            $admins = PurchaseEmailTemplates::adminRecipients();
            if (PurchaseEmailTemplates::enabled('admin') && $admins) {
                Mail::to($admins)->queue(new PurchaseMail(
                    PurchaseEmailTemplates::subject(PurchaseEmailTemplates::get('admin_subject'), $values),
                    PurchaseEmailTemplates::bodyHtml(PurchaseEmailTemplates::get('admin_body'), $values),
                    'New course purchase',
                    filter_var($student->email, FILTER_VALIDATE_EMAIL) ? $student->email : null, // "Reply" goes to the student
                ));
            }

            Enrollment::whereIn('id', $enrollments->pluck('id'))->update(['purchase_email_sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('[purchase-email] could not send for student '.$student->id.': '.$e->getMessage());
        }
    }
}
