<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\User;
use App\Services\Courses\EnrollmentService;
use Illuminate\Console\Command;

/** Creates a student with a completed profile, so a tester can log in straight away (email + code, or phone + OTP). */
class CreateStudent extends Command
{
    protected $signature = 'lms:create-student {login : Email address (or a 10-digit mobile number when LMS_LOGIN_METHOD=phone)}
        {--name=Test Student : Full name}
        {--phone= : Mobile number (optional)}
        {--all-courses : Also assign every existing course}';

    protected $description = 'Create a student (or update the existing one with this login) with a completed profile';

    public function handle(EnrollmentService $enrollments): int
    {
        $login = trim((string) $this->argument('login'));
        $name = (string) $this->option('name');

        if (str_contains($login, '@')) {
            if (! filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $this->error('Enter a valid email address.');

                return self::FAILURE;
            }
            $key = ['email' => mb_strtolower($login)];
            $extra = ['email_verified_at' => now(), 'phone' => $this->option('phone') ?: null];
        } else {
            $phone = substr(preg_replace('/\D+/', '', $login), -10);
            if (strlen($phone) < 10) {
                $this->error('Enter an email address, or a 10-digit mobile number.');

                return self::FAILURE;
            }
            $key = ['phone' => $phone];
            $extra = ['email' => "{$phone}@example.com"];
        }

        $student = User::updateOrCreate($key, $extra + [
            'name' => $name,
            'nickname' => explode(' ', trim($name))[0],
            'target_exam' => 'IMU-CET',
            'attempt_year' => now()->year + 1,
            'profile_completed_at' => now(),
            'is_active' => true,
        ]);
        $this->info(($student->wasRecentlyCreated ? 'Created' : 'Updated')." student {$name} ({$student->loginId()}).");

        if ($this->option('all-courses')) {
            $count = Course::all()->filter(fn (Course $course) => $enrollments->assign($student, $course))->count();
            $this->info("Assigned {$count} course(s).");
        }

        return self::SUCCESS;
    }
}
