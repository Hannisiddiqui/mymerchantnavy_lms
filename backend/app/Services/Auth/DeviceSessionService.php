<?php

namespace App\Services\Auth;

use App\Models\Setting;
use App\Models\User;

/**
 * One Sanctum token = one logged-in device. Tokens never expire: a student stays logged in until they log out.
 * With a device limit set (dynamic Setting or config lms.max_devices > 0), logging in on one more device removes the oldest device.
 */
class DeviceSessionService
{
    public function login(User $user, string $deviceName): string
    {
        $token = $user->createToken(mb_substr($deviceName, 0, 120))->plainTextToken;

        $maxDevices = (int) Setting::get('student_max_devices', config('lms.max_devices', 0));

        if ($maxDevices > 0) {
            $user->tokens()
                ->orderByDesc('id')
                ->get()
                ->slice($maxDevices)
                ->each->delete();
        }

        return $token;
    }
}
