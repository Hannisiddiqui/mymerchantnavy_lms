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
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $token = $this->devices->login($user, $request->validated('device_name') ?? 'Unknown device');

        return response()->json(['data' => [
            'token' => $token,
            'needsProfile' => $user->needsProfile(),
        ]]);
    }

    /** POST /api/v1/auth/logout: logs out this device only. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

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
