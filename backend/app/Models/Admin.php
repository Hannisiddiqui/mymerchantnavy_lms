<?php

namespace App\Models;

use App\Enums\AdminRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

/**
 * Admin panel user (admin.mymerchantnavy.com).
 * - Super admin: strictly support.mymerchantnavy@gmail.com (highest authority, only one who can delete data & set session limits).
 * - Admin: can view and check all data (students, courses, tests, enrollments, reports) but CANNOT delete anything.
 * - Editor: only able to update and change course content (courses, curriculum, tests, questions).
 */
class Admin extends Authenticatable implements FilamentUser
{
    use Notifiable;

    public const SUPER_ADMIN_EMAIL = 'support.mymerchantnavy@gmail.com';

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdminRole::class,
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

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->role === AdminRole::Admin;
    }

    public function isEditor(): bool
    {
        return ! $this->isSuperAdmin() && $this->role === AdminRole::Editor;
    }

    public function canDeleteAnything(): bool
    {
        return $this->isSuperAdmin();
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
        return $this->isAdmin();
    }

    public function canManageStudents(): bool
    {
        return $this->isAdmin();
    }

    public function canManageCourseContent(): bool
    {
        return true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
