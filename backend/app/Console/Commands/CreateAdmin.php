<?php

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Console\Command;

/** Creates (or resets the password of) an admin who can log in to admin.mymerchantnavy.com. */
class CreateAdmin extends Command
{
    protected $signature = 'lms:create-admin {email : Login email} {--name=Admin : Display name} {--password= : Leave empty to be asked} {--role= : admin, manager, support}';

    protected $description = 'Create an admin user, or reset the password of an existing one';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = $this->option('password') ?: $this->secret('Password (at least 12 characters)');

        if (strlen((string) $password) < 12) {
            $this->error('The password must be at least 12 characters.');

            return self::FAILURE;
        }

        $role = $this->option('role');
        $roleEnum = null;
        if ($email === Admin::SUPER_ADMIN_EMAIL) {
            $roleEnum = AdminRole::SuperAdmin;
        } elseif ($role) {
            $roleEnum = AdminRole::tryFrom(strtolower($role)) ?? AdminRole::Admin;
            if ($roleEnum === AdminRole::SuperAdmin) {
                $this->error('The Super Admin role is exclusively reserved for ' . Admin::SUPER_ADMIN_EMAIL);

                return self::FAILURE;
            }
        } else {
            $roleEnum = AdminRole::Admin;
        }

        $admin = Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => $this->option('name'),
                'password' => $password,
                'role' => $roleEnum,
            ]
        );

        $roleLabel = $admin->role?->label() ?? $admin->role;
        $this->info($admin->wasRecentlyCreated ? "Admin {$email} created with role [{$roleLabel}]." : "Password/role of {$email} updated [{$roleLabel}].");

        return self::SUCCESS;
    }
}
