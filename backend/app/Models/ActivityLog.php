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

        return static::create([
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
    }
}
