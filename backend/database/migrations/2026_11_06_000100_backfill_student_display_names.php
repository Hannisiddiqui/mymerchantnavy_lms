<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $users = User::whereNull('name')->orWhere('name', '')->get();

        foreach ($users as $user) {
            $prefix = explode('@', $user->email)[0] ?? '';
            $clean = ucwords(str_replace(['.', '_', '-'], ' ', preg_replace('/[0-9]+$/', '', $prefix)));
            $defaultName = filled($clean) ? $clean : (filled($prefix) ? $prefix : "Student #{$user->id}");

            $user->name = $defaultName;
            if (blank($user->nickname)) {
                $user->nickname = $defaultName;
            }
            $user->saveQuietly();
        }
    }

    public function down(): void
    {
        // No-op to avoid clearing legitimate user names
    }
};
