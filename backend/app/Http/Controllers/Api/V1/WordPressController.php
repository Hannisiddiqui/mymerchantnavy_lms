<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\DeviceSessionService;
use App\Services\WordPress\WordPressLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The link with the WordPress site (mymerchantnavy.com), see docs/WORDPRESS.md.
 * The two /integrations/wordpress/* calls are signed server-to-server messages; /auth/sso signs a student in.
 */
class WordPressController extends Controller
{
    public function __construct(private WordPressLink $link) {}

    /** POST /integrations/wordpress/student: a student registered on WordPress (before paying). No course is given. */
    public function student(Request $request): JsonResponse
    {
        $data = $request->validate($this->studentRules());
        $user = $this->link->student($data['email'], $data['name'] ?? null, $data['phone'] ?? null, $data['wp_user_id'] ?? null);

        return response()->json(['data' => ['id' => $user->id, 'created' => $user->wasRecentlyCreated]]);
    }

    /** POST /integrations/wordpress/enroll: a payment succeeded. Creates the student if needed and gives the course. Safe to repeat. */
    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate($this->studentRules() + ['product_id' => ['required', 'string', 'max:191'], 'order_id' => ['nullable', 'string', 'max:191']]);

        abort_if(! $this->link->coursesFor($data['product_id']), 422, "No course is linked to WordPress product {$data['product_id']}. Set it on the course in the admin panel.");
        $user = $this->link->student($data['email'], $data['name'] ?? null, $data['phone'] ?? null, $data['wp_user_id'] ?? null);

        return response()->json(['data' => ['id' => $user->id, 'newCourses' => $this->link->enroll($user, $data['product_id'])]]);
    }

    /** POST /auth/sso {token}: the "go to my course" link from WordPress. Same answer as the code login. */
    public function sso(Request $request, DeviceSessionService $devices): JsonResponse
    {
        abort_unless($this->link->enabled(), 503, 'The WordPress link is not set up.');
        $token = (string) $request->validate(['token' => ['required', 'string', 'max:1000']])['token'];
        $email = $this->link->emailFromToken($token);
        abort_unless($email, 401, 'This sign-in link has expired. Please log in with your email.');

        $user = $this->link->student($email);
        abort_unless($user->is_active, 403, 'Your account is disabled. Please contact support.');

        return response()->json(['data' => [
            'token' => $devices->login($user, $request->input('device_name') ?? 'Unknown device'),
            'needsProfile' => $user->needsProfile(),
        ]]);
    }

    private function studentRules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:191'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'wp_user_id' => ['nullable', 'integer'],
        ];
    }
}
