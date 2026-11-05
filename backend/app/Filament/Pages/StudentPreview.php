<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\User;
use App\Services\Courses\EnrollmentService;
use App\Services\WordPress\WordPressLink;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * "View as student": opens the student site signed in as a private preview student who has this one course, so whoever
 * builds a course sees exactly what a student sees. The preview student is never counted in students, purchases,
 * results or rankings.
 */
class StudentPreview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'View as student';

    protected static ?string $navigationLabel = 'View as student';

    protected string $view = 'filament.pages.student-preview';

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return (bool) ($a && ($a->isSuperAdmin() || $a->isStudentPreview() || $a->hasPermission('manage_courses') || $a->hasPermission('manage_tests')));
    }

    /** @return \Illuminate\Support\Collection<int,Course> */
    public function courses()
    {
        return Course::where('is_active', true)->orderBy('title')->get(['id', 'title']);
    }

    public function open(int $courseId): void
    {
        abort_unless(static::canAccess(), 403);
        $link = app(WordPressLink::class);
        if (! $link->enabled()) {
            Notification::make()->title('Preview needs the WordPress link')->body('Set LMS_WP_SECRET in the server .env (the same secret the WordPress plugin uses), then try again.')->danger()->send();

            return;
        }

        $admin = auth('admin')->user();
        $course = Course::where('is_active', true)->findOrFail($courseId);

        $email = "preview-{$admin->id}@preview.mymerchantnavy.invalid";
        $student = User::whereRaw('lower(email) = ?', [$email])->first() ?? new User(['email' => $email]);
        $student->forceFill([
            'name' => 'Preview: '.$admin->name,
            'nickname' => 'Preview',
            'is_preview' => true,
            'is_active' => true,
            'email_verified_at' => $student->email_verified_at ?: now(),
            'profile_completed_at' => $student->profile_completed_at ?: now(),
        ])->save();

        // A day's access to this one course, renewed every time the preview is opened.
        app(EnrollmentService::class)->assign($student, $course, $admin, explicitExpiresAt: now()->addDay(), sendCourseEmail: false);

        ActivityLog::record(
            event: 'preview.opened',
            description: "{$admin->name} opened the student view of '{$course->title}'.",
            properties: ['course_id' => $course->id],
            admin: $admin
        );

        $token = $link->makeToken($email, null, true, "/my-courses/{$course->id}");
        $this->redirect(rtrim((string) config('lms.student_app_url'), '/').'/sso#t='.$token);
    }
}
