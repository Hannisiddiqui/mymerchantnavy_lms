<?php

namespace App\Http\Middleware;

use App\Services\WordPress\WordPressLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only the WordPress site (which knows the shared secret) may call the integration endpoints. */
class VerifyWordPressSignature
{
    public function __construct(private WordPressLink $link) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->link->enabled(), 503, 'The WordPress link is not set up.');
        abort_unless($this->link->validRequest(
            (string) $request->header('X-MMN-Timestamp'),
            (string) $request->header('X-MMN-Signature'),
            $request->getContent(),
        ), 401, 'Invalid signature.');

        return $next($request);
    }
}
