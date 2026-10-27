<?php

use App\Http\Controllers\Api\V1\AttemptController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PdfPageController;
use App\Http\Controllers\Api\V1\PerformanceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\QuestionReportController;
use App\Http\Controllers\Api\V1\TestController;
use App\Http\Controllers\Api\V1\WordPressController;
use App\Http\Middleware\VerifyWordPressSignature;
use Illuminate\Support\Facades\Route;

/*
| Student API — https://api.mymerchantnavy.com/api/v1/...
| Used by the student app (student-app/src/lib/api) and later the mobile apps.
| Every response is JSON shaped as { "data": ... }.
| Full endpoint list: docs/PLAN.md section 8.
*/

Route::prefix('v1')->group(function () {
    // ---- Login (public, rate-limited) ----
    Route::middleware('throttle:otp')->group(function () {
        Route::post('auth/otp/send', [AuthController::class, 'sendOtp']);
        Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp']);
    });

    // ---- WordPress link (docs/WORDPRESS.md): signed messages from the shop, and the one-time sign-in link ----
    Route::post('auth/sso', [WordPressController::class, 'sso'])->middleware('throttle:otp');
    Route::prefix('integrations/wordpress')->middleware([VerifyWordPressSignature::class, 'throttle:120,1'])->group(function () {
        Route::post('student', [WordPressController::class, 'student']);
        Route::post('enroll', [WordPressController::class, 'enroll']);
    });

    // ---- Protected PDF page images: signed, expiring link (an <img> tag can't send the login token) ----
    Route::get('pdf-pages/{content}/{page}', [PdfPageController::class, 'show'])
        ->whereNumber(['content', 'page'])->middleware('signed')->name('pdf.page');

    // ---- Logged-in student ----
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Profile & devices
        Route::get('me', [ProfileController::class, 'show']);
        Route::put('me', [ProfileController::class, 'update']);
        Route::get('me/devices', [ProfileController::class, 'devices']);
        Route::delete('me/devices/{id}', [ProfileController::class, 'destroyDevice'])->whereNumber('id');

        // Learning: courses → subjects → content
        Route::get('courses', [CourseController::class, 'index']);
        Route::get('courses/{id}', [CourseController::class, 'show'])->whereNumber('id');
        Route::get('subjects/{id}', [CourseController::class, 'subject'])->whereNumber('id');
        Route::get('contents/{id}', [ContentController::class, 'show'])->whereNumber('id');
        Route::post('contents/{id}/complete', [ContentController::class, 'complete'])->whereNumber('id');
        Route::get('contents/{id}/pages', [ContentController::class, 'pages'])->whereNumber('id');
        Route::post('contents/{id}/submission', [ContentController::class, 'submitAssignment'])->whereNumber('id');

        // Tests
        Route::get('mock-tests', [TestController::class, 'mockTests']);
        Route::get('tests/{id}', [TestController::class, 'show'])->whereNumber('id');
        Route::post('tests/{test}/attempts', [AttemptController::class, 'start'])->whereNumber('test');
        Route::put('attempts/{attempt}/answers/{question}', [AttemptController::class, 'saveAnswer'])->whereNumber('question');
        Route::post('attempts/{attempt}/submit', [AttemptController::class, 'submit']);
        Route::get('attempts/{attempt}/result', [AttemptController::class, 'result']);
        Route::post('attempts/{attempt}/questions/{question}/report', [QuestionReportController::class, 'store'])
            ->whereNumber('question')->middleware('throttle:30,1');

        // Results and the performance dashboard
        Route::get('results', [AttemptController::class, 'index']);
        Route::get('performance', [PerformanceController::class, 'show']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'markAllRead']);
    });
});
