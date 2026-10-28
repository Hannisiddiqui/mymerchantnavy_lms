<?php

namespace App\Models;

use App\Enums\AdminRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'actor_email',
        'actor_name',
        'actor_role',
        'event',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record an activity on the platform.
     */
    public static function record(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?Admin $admin = null,
        ?User $student = null
    ): static {
        $req = request();

        $actorType = 'system';
        $actorId = null;
        $actorEmail = null;
        $actorName = null;
        $actorRole = null;

        $currentAdmin = $admin ?? (auth('admin')->check() ? auth('admin')->user() : null);
        $currentStudent = $student ?? (auth('sanctum')->check() ? auth('sanctum')->user() : ($req?->user() instanceof User ? $req->user() : null));

        if ($currentAdmin instanceof Admin) {
            $actorType = 'admin';
            $actorId = $currentAdmin->id;
            $actorEmail = $currentAdmin->email;
            $actorName = $currentAdmin->name;
            $actorRole = $currentAdmin->isSuperAdmin() ? AdminRole::SuperAdmin->value : ($currentAdmin->role ?? AdminRole::Admin->value);
        } elseif ($currentStudent instanceof User) {
            $actorType = 'student';
            $actorId = $currentStudent->id;
            $actorEmail = $currentStudent->email ?? $currentStudent->phone;
            $actorName = $currentStudent->name ?? 'Student #'.$currentStudent->id;
            $actorRole = 'student';
        }

        $log = static::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_email' => $actorEmail,
            'actor_name' => $actorName,
            'actor_role' => $actorRole,
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => ! empty($properties) ? $properties : null,
            'ip_address' => $req?->ip(),
            'user_agent' => $req?->userAgent() ? mb_substr($req->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);

        static::dispatchNotificationEmail($log);

        return $log;
    }

    /**
     * Dispatch email notification on recorded changes if enabled in settings.
     */
    protected static function dispatchNotificationEmail(self $log): void
    {
        if (str_starts_with($log->event, 'email.') || str_starts_with($log->event, 'auth.check')) {
            return;
        }

        try {
            $enabled = filter_var(Setting::get('audit_notifications_enabled', true), FILTER_VALIDATE_BOOLEAN);
            if (! $enabled) {
                return;
            }

            $emailsRaw = Setting::get('audit_notification_emails', 'support.mymerchantnavy@gmail.com');
            $emails = array_filter(array_map('trim', explode(',', (string) $emailsRaw)));

            if (empty($emails)) {
                return;
            }

            $subject = "[MMN Audit Alert] {$log->event} by " . ($log->actor_name ?: $log->actor_email ?: 'System');
            $propsFormatted = ! empty($log->properties) ? json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'None';

            $body = "A change or administrative event was recorded on the LMS Platform:\n\n"
                . "Event: {$log->event}\n"
                . "Description: {$log->description}\n"
                . "Actor: {$log->actor_name} ({$log->actor_email}) [Role: {$log->actor_role}]\n"
                . "IP Address: " . ($log->ip_address ?: 'Unknown') . "\n"
                . "Device / User-Agent: " . ($log->user_agent ?: 'Unknown') . "\n"
                . "Timestamp: " . ($log->created_at?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s')) . " UTC\n\n"
                . "Changes & Metadata:\n"
                . "{$propsFormatted}\n\n"
                . "---\n"
                . "My Merchant Navy LMS Security & Audit System\n"
                . "https://api.mymerchantnavy.com/admin/website-activity";

            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    \Illuminate\Support\Facades\Mail::raw($body, function ($msg) use ($email, $subject) {
                        $msg->to($email)->subject($subject);
                    });
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
