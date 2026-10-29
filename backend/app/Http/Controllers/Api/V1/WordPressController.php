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

    /** POST /integrations/wordpress/check: check if a student exists and if they already purchased this product. */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:191'],
            'product_id' => ['nullable', 'string', 'max:191'],
        ]);

        $res = $this->link->check($data['email'], $data['product_id'] ?? null);

        return response()->json(['data' => $res]);
    }

    /** POST /integrations/wordpress/student: a student registered on WordPress (before paying). No course is given. */
    public function student(Request $request): JsonResponse
    {
        $data = $request->validate($this->studentRules());
        $user = $this->link->student($data['email'], $data['name'] ?? null, $data['phone'] ?? null, $data['wp_user_id'] ?? null, (bool) ($data['email_verified'] ?? false));

        return response()->json(['data' => ['id' => $user->id, 'created' => $user->wasRecentlyCreated]]);
    }

    /** POST /integrations/wordpress/enroll: a payment succeeded. Creates the student if needed and gives the course. Safe to repeat. */
    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate($this->studentRules() + [
            'product_id' => ['required', 'string', 'max:191'],
            'order_id' => ['nullable', 'string', 'max:191'],
            'payment_id' => ['nullable', 'string', 'max:191'],
            'amount' => ['nullable', 'numeric'],
            'purchase_date' => ['nullable', 'date'],
        ]);

        abort_if(! $this->link->coursesFor($data['product_id']), 422, "No course is linked to WordPress product {$data['product_id']}. Set it on the course in the admin panel.");
        $user = $this->link->student($data['email'], $data['name'] ?? null, $data['phone'] ?? null, $data['wp_user_id'] ?? null, (bool) ($data['email_verified'] ?? false));

        $newCourses = $this->link->enroll(
            student: $user,
            productId: $data['product_id'],
            orderId: $data['order_id'] ?? null,
            paymentId: $data['payment_id'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            purchaseDate: isset($data['purchase_date']) ? new \DateTime($data['purchase_date']) : null
        );
        // every course this product gives (not only the new ones), so WordPress can send the student to it
        $courses = array_map(fn ($c) => ['id' => $c->id, 'title' => $c->title], $this->link->coursesFor($data['product_id']));

        return response()->json(['data' => ['id' => $user->id, 'newCourses' => $newCourses, 'courses' => $courses]]);
    }

    /** POST /auth/sso {token}: the "go to my course" link from WordPress. Same answer as the code login. */
    public function sso(Request $request, DeviceSessionService $devices): JsonResponse
    {
        abort_unless($this->link->enabled(), 503, 'The WordPress link is not set up.');
        $token = (string) $request->validate(['token' => ['required', 'string', 'max:1000']])['token'];
        $claims = $this->link->claimsFromToken($token);
        abort_unless($claims, 401, 'This sign-in link has expired. Please log in with your email.');

        $user = $this->link->student(
            email: $claims['email'],
            name: $claims['name'] ?? null,
            phone: $claims['phone'] ?? null,
            verified: $claims['verified']
        );
        abort_unless($user->is_active, 403, 'Your account is disabled. Please contact support.');
        // A link from a WordPress account whose email was NOT verified there (an old or other kind of registration) must
        // never open an account whose owner has proved the address; only the emailed code does that.
        abort_if($user->email_verified_at && ! $claims['verified'], 401, 'For your security, please log in with the code we email you.');

        return response()->json(['data' => [
            'token' => $devices->login($user, $request->input('device_name') ?? 'Unknown device'),
            'needsProfile' => $user->needsProfile(),
            'redirect' => $claims['go'],
        ]]);
    }

    private function studentRules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:191'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'wp_user_id' => ['nullable', 'integer'],
            'email_verified' => ['nullable', 'boolean'],
        ];
    }
}
