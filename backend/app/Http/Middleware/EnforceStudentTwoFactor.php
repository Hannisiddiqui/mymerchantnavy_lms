<?php

namespace App\Http\Middleware;

use App\Services\Auth\StudentTwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When two-step verification is compulsory, a logged-in student who has not set it up can reach nothing except the
 * set-up calls, their basic profile and logout. Checked on the server, so skipping the set-up screen gets them nowhere.
 */
class EnforceStudentTwoFactor
{
    public function __construct(private StudentTwoFactor $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && StudentTwoFactor::requiredFor($user) && ! $this->twoFactor->enabledFor($user)
            && ! $request->is('api/v1/me/two-factor*', 'api/v1/auth/logout')
            && ! ($request->isMethod('GET') && $request->is('api/v1/me'))) {
            return response()->json([
                'message' => 'Please set up two-step verification to continue.',
                'code' => 'two_factor_setup_required',
            ], 403);
        }

        return $next($request);
    }
}
