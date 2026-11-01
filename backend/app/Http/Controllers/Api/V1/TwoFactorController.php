<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Auth\DeviceSessionService;
use App\Services\Auth\StudentTwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Student two-step verification: status, set up, confirm, turn off, and the code step of a login. */
class TwoFactorController extends Controller
{
    public function __construct(private StudentTwoFactor $twoFactor) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'available' => StudentTwoFactor::featureOn(),
            'required' => StudentTwoFactor::requiredFor($request->user()),
            'enabled' => $this->twoFactor->enabledFor($request->user()),
        ]]);
    }

    public function setup(Request $request): JsonResponse
    {
        abort_unless(StudentTwoFactor::featureOn(), 403, 'Two-step verification is not available right now.');
        abort_if($this->twoFactor->enabledFor($request->user()), 422, 'Two-step verification is already on. Turn it off first to set it up again.');

        return response()->json(['data' => $this->twoFactor->begin($request->user())]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:20']])['code'];
        $codes = $this->twoFactor->confirm($request->user(), $code);
        abort_unless($codes, 422, 'That code is not right. Check the code in your app and try again.');

        return response()->json(['data' => ['recoveryCodes' => $codes]]);
    }

    public function disable(Request $request): JsonResponse
    {
        abort_if(StudentTwoFactor::requiredFor($request->user()), 403, 'Two-step verification is compulsory for all students and cannot be turned off.');
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:20']])['code'];
        abort_unless($this->twoFactor->disable($request->user(), $code), 422, 'That code is not right.');

        return response()->json(['data' => null]);
    }

    /** POST /auth/2fa/challenge {challenge, code}: the second step of a login. Same answer as the normal login. */
    public function challenge(Request $request, DeviceSessionService $devices): JsonResponse
    {
        $data = $request->validate(['challenge' => ['required', 'string', 'max:100'], 'code' => ['required', 'string', 'max:20']]);
        $result = $this->twoFactor->finishChallenge($data['challenge'], $data['code']);
        abort_unless($result, 422, 'That code is not right or has expired. Please log in again.');

        $user = $result['user'];
        ActivityLog::record(
            event: 'student.login',
            description: "Student {$user->email} logged in with two-step verification on device: {$result['device']}",
            subject: $user,
            properties: ['device_name' => $result['device'], 'two_factor' => true],
            student: $user
        );

        return response()->json(['data' => [
            'token' => $devices->login($user, $result['device']),
            'needsProfile' => $user->needsProfile(),
        ]]);
    }
}
