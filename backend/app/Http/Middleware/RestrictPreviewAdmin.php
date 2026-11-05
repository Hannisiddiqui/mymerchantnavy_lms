<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** An admin with the "Student (view only)" role can only open the student preview, their profile and log out. */
class RestrictPreviewAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();
        if ($admin && $admin->isStudentPreview()) {
            $path = trim($request->path(), '/');
            $allowed = str_starts_with($path, 'livewire') || str_contains($path, 'student-preview') || str_contains($path, 'profile')
                || str_contains($path, 'logout') || str_contains($path, 'multi-factor');
            if (! $allowed) {
                return redirect('/'.trim((string) config('lms.admin_domain') ? '' : 'admin', '/').'/student-preview');
            }
        }

        return $next($request);
    }
}
