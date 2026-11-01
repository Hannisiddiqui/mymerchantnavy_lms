<?php

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case SiteManager = 'site_manager';
    case Support = 'support';
    case StudentPreview = 'student_preview';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::SiteManager => 'Site Manager',
            self::Support => 'Support',
            self::StudentPreview => 'Student (view only)',
        };
    }
}
