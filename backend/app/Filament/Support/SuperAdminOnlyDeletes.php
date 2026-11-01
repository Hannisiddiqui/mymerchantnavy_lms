<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Deleting is permanent (nothing is kept in a bin), so only the Super Admin can do it: courses, tests, questions,
 * batches, pages, imports, support queries and students. Every other admin can create and edit, but not delete.
 */
trait SuperAdminOnlyDeletes
{
    public static function canDelete(Model $record): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }
}
