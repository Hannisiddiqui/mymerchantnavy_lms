<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\Auth\DeviceSessionService;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Email + one-time code login (the default). The mobile number + OTP login is still here, switched on with
 * LMS_LOGIN_METHOD=phone.
 */
class AuthController extends Controller
{
    public function __construct(private OtpService $otp, private DeviceSessionService $devices) {}

    /** POST /api/v1/auth/otp/send */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $this->otp->send($request->validated(OtpService::column()));

        return response()->json(['data' => null, 'message' => OtpService::column() === 'email' ? 'Code emailed' : 'OTP sent']);
    }

    /** POST /api/v1/auth/otp/verify: creates the student on first login. The device stays logged in until they log out. */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $column = OtpService::column();
        $identifier = $request->validated($column);
        $this->otp->verify($identifier, $request->validated('code'));

        $user = $this->findOrCreate($column, $identifier);
        abort_unless($user->is_active, 403, 'Your account is disabled. Please contact support.');

        // Receiving the code proves the address is theirs.
        if ($column === 'email' && ! $user->email_verified_at) {
            // Sessions made before the owner proved the address (e.g. from the WordPress sign-in link) are not trusted.
            $user->tokens()->delete();
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $deviceName = $request->validated('device_name') ?? 'Unknown device';
        $token = $this->devices->login($user, $deviceName);

        \App\Models\ActivityLog::record(
            event: 'student.login',
            description: "Student {$user->email} logged in on device: {$deviceName}",
            subject: $user,
            properties: ['device_name' => $deviceName],
            student: $user
        );

        $deviceLimitEnabled = filter_var(\App\Models\Setting::get('device_limit_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $studentMaxDevices = (int) \App\Models\Setting::get('student_max_devices', config('lms.max_devices', 2) ?: 2);
        $tabLimitEnabled = filter_var(\App\Models\Setting::get('tab_limit_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $studentMaxTabs = (int) \App\Models\Setting::get('student_max_tabs', 2);

        return response()->json(['data' => [
            'token' => $token,
            'needsProfile' => $user->needsProfile(),
            'sessionPolicy' => [
                'device_limit_enabled' => $deviceLimitEnabled,
                'student_max_devices' => $studentMaxDevices,
                'tab_limit_enabled' => $tabLimitEnabled,
                'student_max_tabs' => $studentMaxTabs,
            ],
        ]]);
    }

    /** GET /api/v1/session-policy: returns device and tab limiting configuration for the client app. */
    public function sessionPolicy(): JsonResponse
    {
        $deviceLimitEnabled = filter_var(\App\Models\Setting::get('device_limit_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $studentMaxDevices = (int) \App\Models\Setting::get('student_max_devices', config('lms.max_devices', 2) ?: 2);
        if ($studentMaxDevices <= 0) {
            $studentMaxDevices = 2;
        }

        $tabLimitEnabled = filter_var(\App\Models\Setting::get('tab_limit_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $studentMaxTabs = (int) \App\Models\Setting::get('student_max_tabs', 2);
        if ($studentMaxTabs <= 0) {
            $studentMaxTabs = 2;
        }

        return response()->json([
            'data' => [
                'device_limit_enabled' => $deviceLimitEnabled,
                'student_max_devices' => $studentMaxDevices,
                'tab_limit_enabled' => $tabLimitEnabled,
                'student_max_tabs' => $studentMaxTabs,
            ],
        ]);
    }

    /** POST /api/v1/auth/logout: logs out this device only. */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $deviceName = $token?->name ?? 'Unknown device';

        \App\Models\ActivityLog::record(
            event: 'student.logout',
            description: "Student {$user->email} logged out from device: {$deviceName}",
            subject: $user,
            properties: ['device_name' => $deviceName],
            student: $user
        );

        $token?->delete();

        return response()->json(['data' => null]);
    }

    /** Emails are matched ignoring case (an admin may have typed "Riya@Example.com"). */
    private function findOrCreate(string $column, string $identifier): User
    {
        if ($column === 'phone') {
            return User::firstOrCreate(['phone' => $identifier]);
        }

        return User::whereRaw('lower(email) = ?', [$identifier])->first() ?? User::create(['email' => $identifier]);
    }
}
