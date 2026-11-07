<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\StudentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The logged-in student's profile and devices. */
class ProfileController extends Controller
{
    /** GET /api/v1/me */
    public function show(Request $request): StudentResource
    {
        return new StudentResource($request->user());
    }

    /** PUT /api/v1/me */
    public function update(UpdateProfileRequest $request): StudentResource
    {
        $user = $request->user();
        $data = $request->validated();

        $update = [];
        if (isset($data['name'])) {
            $update['name'] = $data['name'];
        }
        if (isset($data['nickname'])) {
            $update['nickname'] = $data['nickname'];
        }
        if (isset($data['targetExam'])) {
            $update['target_exam'] = $data['targetExam'];
        }
        if (isset($data['attemptYear'])) {
            $update['attempt_year'] = $data['attemptYear'];
        }

        if (config('lms.login_method') === 'phone') {
            if (isset($data['email'])) {
                $update['email'] = $data['email'];
            }
        } else {
            if (array_key_exists('phone', $data)) {
                $update['phone'] = filled($data['phone']) ? $data['phone'] : null;
            }
        }

        $update['profile_completed_at'] = $user->profile_completed_at ?? now();

        $user->update($update);

        return new StudentResource($user->fresh());
    }

    /** GET /api/v1/me/devices */
    public function devices(Request $request): AnonymousResourceCollection
    {
        return DeviceResource::collection($request->user()->tokens()->latest('id')->get());
    }

    /** DELETE /api/v1/me/devices/{id} — log out another device. */
    public function destroyDevice(Request $request, int $id): JsonResponse
    {
        $request->user()->tokens()->whereKey($id)->delete();

        return response()->json(['data' => null]);
    }
}
