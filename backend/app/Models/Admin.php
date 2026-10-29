<?php

namespace App\Models;

use App\Enums\AdminRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

/**
 * Admin panel user (admin.mymerchantnavy.com).
 * Super admin is strictly support.mymerchantnavy@gmail.com with highest authority.
 */
class Admin extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    public const SUPER_ADMIN_EMAIL = 'support.mymerchantnavy@gmail.com';

    protected $fillable = ['name', 'email', 'password', 'role', 'permissions'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdminRole::class,
            'permissions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $admin) {
            $isSuperEmail = strtolower((string) $admin->email) === self::SUPER_ADMIN_EMAIL;

            if ($isSuperEmail) {
                $admin->role = AdminRole::SuperAdmin;
            } elseif ($admin->role === AdminRole::SuperAdmin) {
                // Only support.mymerchantnavy@gmail.com can hold the SuperAdmin role
                throw ValidationException::withMessages([
                    'role' => 'The Super Admin role is exclusively reserved for '.self::SUPER_ADMIN_EMAIL.'.',
                ]);
            }
        });

        static::deleting(function (self $admin) {
            if ($admin->isSuperAdmin()) {
                throw new \Exception('The Super Admin account cannot be deleted.');
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return strtolower((string) $this->email) === self::SUPER_ADMIN_EMAIL;
    }

    public function isSiteManager(): bool
    {
        return $this->role === AdminRole::SiteManager;
    }

    /**
     * Check if the admin holds a specific granular permission.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (is_array($this->permissions)) {
            return in_array($permission, $this->permissions, true);
        }

        // Default role fallbacks if permissions array is null:
        return match ($this->role) {
            AdminRole::Admin => in_array($permission, ['manage_courses', 'manage_tests', 'manage_batches', 'manage_page_builder', 'manage_students', 'manage_reports', 'can_publish_without_approval'], true),
            AdminRole::Manager, AdminRole::SiteManager => in_array($permission, ['manage_support_chat', 'manage_reports', 'manage_students'], true),
            AdminRole::Support => in_array($permission, ['manage_support_chat', 'manage_reports'], true),
            default => false,
        };
    }

    public function canPublishDirectly(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasPermission('can_publish_without_approval');
    }

    public function canManageAdmins(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canDeleteStudents(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canConfigureSessionLimits(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canViewActivityLogs(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
