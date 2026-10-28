<?php

namespace App\Services\Courses;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\CourseAssigned;
use Carbon\Carbon;

/** Gives a student access to a course (admin assignment or purchase webhook). */
class EnrollmentService
{
    /**
     * Assign or enroll a student in a course with automatic calendar-based validity calculation.
     *
     * @return bool false when the student already had this active enrollment (idempotent for webhook retries)
     */
    public function assign(
        User $student,
        Course $course,
        ?Admin $by = null,
        ?\DateTimeInterface $purchaseDate = null,
        string $purchaseType = 'assigned',
        ?string $orderId = null,
        ?string $paymentId = null,
        ?float $amount = null,
        ?\DateTimeInterface $explicitExpiresAt = null,
    ): bool {
        $actualPurchaseDate = $purchaseDate ? Carbon::parse($purchaseDate) : now();

        // Calculate calendar-based expiry using course validity rules
        $expiresAt = $explicitExpiresAt
            ? Carbon::parse($explicitExpiresAt)
            : $course->calculateExpiry($actualPurchaseDate);

        $existing = Enrollment::where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            // Idempotency: a webhook retry with the same order id changes nothing
            if ($orderId && $existing->order_id === $orderId) {
                return false;
            }

            $wasActive = $existing->isActive();
            $isPurchase = (bool) $orderId;
            $isAdminRenewal = ! $orderId && $by !== null && ! $wasActive;

            // Nothing to do: an admin re-assigning a course the student still has
            if (! $isPurchase && ! $isAdminRenewal) {
                return false;
            }

            // Renewal policy (documented in docs/HANDOFF.md):
            //  - a new paid order while the course is still active EXTENDS from the current expiry, so no paid days are lost;
            //    a lifetime enrollment stays lifetime;
            //  - an expired or cancelled enrollment starts again from the new purchase date;
            //  - an admin re-assigning an expired course starts again from today.
            $previous = $existing->expires_at;
            if ($explicitExpiresAt) {
                $newExpiry = Carbon::parse($explicitExpiresAt);
            } elseif ($wasActive && $isPurchase) {
                $newExpiry = $previous === null ? null : $course->calculateExpiry($previous);
            } else {
                $newExpiry = $course->calculateExpiry($isAdminRenewal ? now() : $actualPurchaseDate);
            }

            $existing->update([
                'purchase_date' => $isAdminRenewal ? now() : $actualPurchaseDate,
                'expires_at' => $newExpiry,
                'purchase_type' => $isAdminRenewal ? 'assigned' : $purchaseType,
                'order_id' => $orderId ?? $existing->order_id,
                'payment_id' => $paymentId ?: $existing->payment_id,
                'amount' => $amount ?: $existing->amount,
                'assigned_by' => $isAdminRenewal ? $by->id : $existing->assigned_by,
                'status' => 'active',
            ]);

            ActivityLog::record(
                event: 'enrollment.renewed',
                description: "Course '{$course->title}' renewed for {$student->name} ({$student->email}) until ".($newExpiry ? $newExpiry->format('d M Y') : 'Lifetime').($orderId ? " (Order #{$orderId})" : ' (by admin)').'.',
                subject: $existing,
                properties: [
                    'course_id' => $course->id,
                    'student_id' => $student->id,
                    'order_id' => $orderId,
                    'previous_expires_at' => $previous?->toIso8601String(),
                    'expires_at' => $newExpiry?->toIso8601String(),
                    'purchase_type' => $purchaseType,
                    'policy' => $wasActive && $isPurchase ? 'extended_from_current_expiry' : 'restarted',
                ],
                admin: $by,
                student: $student
            );

            $student->notify(new CourseAssigned($course, $newExpiry, renewed: true));

            return true;
        }
        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'assigned_by' => $by?->id,
            'assigned_at' => now(),
            'purchase_date' => $actualPurchaseDate,
            'expires_at' => $expiresAt,
            'purchase_type' => $purchaseType,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'status' => 'active',
        ]);

        ActivityLog::record(
            event: 'enrollment.created',
            description: "Enrolled {$student->name} ({$student->email}) in '{$course->title}' ({$purchaseType}) valid until " . ($expiresAt ? $expiresAt->format('d M Y') : 'Lifetime') . ($orderId ? " [Order #{$orderId}]" : '') . '.',
            subject: $enrollment,
            properties: [
                'course_id' => $course->id,
                'student_id' => $student->id,
                'order_id' => $orderId,
                'expires_at' => $expiresAt?->toIso8601String(),
                'purchase_type' => $purchaseType,
            ],
            admin: $by,
            student: $student
        );

        $student->notify(new CourseAssigned($course, $expiresAt));

        return true;
    }

    public function remove(User $student, Course $course, ?Admin $by = null): void
    {
        $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
        if ($enrollment) {
            $enrollment->delete();

            ActivityLog::record(
                event: 'enrollment.removed',
                description: "Revoked access to '{$course->title}' from {$student->name} ({$student->email}).",
                subject: $course,
                properties: ['course_id' => $course->id, 'student_id' => $student->id],
                admin: $by,
                student: $student
            );
        }
    }
}
