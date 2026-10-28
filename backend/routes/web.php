<?php

use Illuminate\Support\Facades\Route;

// The front door: send people to the right place instead of showing a placeholder page.
Route::get('/', function () {
    $adminHost = config('lms.admin_domain');

    if (! $adminHost) {
        return redirect('/admin');
    }

    return request()->getHost() === $adminHost
        ? redirect('/login')
        : redirect()->away(config('lms.student_app_url'));
});
