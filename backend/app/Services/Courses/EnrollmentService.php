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
            // Idempotency: If webhook retries with the same order_id, return false without altering
            if ($orderId && $existing->order_id === $orderId) {
                return false;
            }

            // If it's an explicit renewal or repurchase (new order_id), calculate new expiry
            if ($orderId && $orderId !== $existing->order_id) {
                $existing->update([
                    'purchase_date' => $actualPurchaseDate,
                    'expires_at' => $expiresAt,
                    'purchase_type' => $purchaseType,
                    'order_id' => $orderId,
                    'payment_id' => $paymentId ?: $existing->payment_id,
                    'amount' => $amount ?: $existing->amount,
                    'status' => 'active',
                ]);

                ActivityLog::record(
                    event: 'enrollment.renewed',
                    description: "Course '{$course->title}' renewed for {$student->name} ({$student->email}) until " . ($expiresAt ? $expiresAt->format('d M Y') : 'Lifetime') . " (Order #{$orderId}).",
                    subject: $existing,
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

                return true;
            }

            return false;
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

        $student->notify(new CourseAssigned($course));

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
