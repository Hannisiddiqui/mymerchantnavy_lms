<?php

namespace Database\Seeders;

use App\Enums\TestKind;
use App\Models\Admin;
use App\Models\ChapterContent;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Note;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Test;
use App\Models\User;
use App\Models\Video;
use App\Services\Courses\EnrollmentService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * LOCAL DEVELOPMENT DATA ONLY.
     * Admin login: admin@mymerchantnavy.com / password  (change or remove before going live).
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Demo data is never created in production. Create your admin with: php artisan lms:create-admin you@example.com');

            return;
        }

        $superAdmin = Admin::firstOrCreate(
            ['email' => Admin::SUPER_ADMIN_EMAIL],
            ['name' => 'Super Admin', 'password' => 'password']
        );

        $admin = Admin::firstOrCreate(
            ['email' => 'admin@mymerchantnavy.com'],
            ['name' => 'Admin', 'password' => 'password']
        );

        $students = collect([
            ['9876543210', 'Aarav Sharma', 'Aarav', 'aarav@example.com'],
            ['9123456780', 'Riya Patel', 'Riya', 'riya@example.com'],
        ])->map(fn ($s) => User::factory()->create(['phone' => $s[0], 'name' => $s[1], 'nickname' => $s[2], 'email' => $s[3]]));
        User::factory()->withoutProfile()->create(['phone' => '9000011111', 'email' => 'newstudent@example.com']); // registered, waiting for a course

        // ---- A small question bank ----
        $bank = collect([
            ['The SI unit of surface tension is:', ['$\mathrm{N/m^2}$', '$\mathrm{N/m}$', '$\mathrm{J/m}$', '$\mathrm{kg\,m/s}$'], 1, 'Surface tension is force per unit length: $$T = \frac{F}{L}$$ so the unit is $\mathrm{N/m}$.'],
            ['Dimensional formula of Planck\'s constant $h$ is:', ['$[MLT^{-1}]$', '$[ML^2T^{-2}]$', '$[ML^2T^{-1}]$', '$[ML^{-1}T^{-1}]$'], 2, 'From $E = h\nu$: $$h = \frac{[ML^2T^{-2}]}{[T^{-1}]} = [ML^2T^{-1}]$$'],
            ['If the momentum of a body is doubled, its kinetic energy becomes:', ['Double', 'Half', 'Four times', 'Unchanged'], 2, '$K = \dfrac{p^2}{2m}$, so $K \propto p^2$.'],
            ['The pH of a $10^{-3}$ M HCl solution is:', ['1', '3', '11', '7'], 1, '$\text{pH} = -\log_{10}(10^{-3}) = 3$'],
            ['$$\int_0^{\pi/2} \sin^2 x \, dx = \;?$$', ['$\dfrac{\pi}{2}$', '$\dfrac{\pi}{4}$', '$1$', '$\dfrac{\pi}{8}$'], 1, 'Use $\sin^2 x = \dfrac{1-\cos 2x}{2}$ to get $\dfrac{\pi}{4}$.'],
            ['One nautical mile equals:', ['1609 m', '1852 m', '1000 m', '1760 m'], 1, 'By definition $1\,\text{nautical mile} = 1852\,\text{m}$.'],
        ])->map(function ($q) {
            $question = Question::create(['body' => $q[0], 'explanation' => $q[3], 'difficulty' => 'medium']);
            foreach ($q[1] as $i => $text) {
                $question->options()->create(['position' => $i + 1, 'body' => $text, 'is_correct' => $i === $q[2]]);
            }

            return $question;
        });

        // ---- Course 1: IMU-CET ----
        $imu = Course::create(['title' => 'IMU-CET 2027 Complete Course', 'slug' => 'imu-cet-2027', 'description' => 'Physics, Chemistry, Mathematics and English for the IMU Common Entrance Test.']);

        $practice = Test::create(['course_id' => $imu->id, 'title' => 'Units & Dimensions — Practice', 'kind' => TestKind::Practice, 'duration_minutes' => 10, 'is_published' => true]);
        $mock = Test::create(['course_id' => $imu->id, 'title' => 'IMU-CET Mock Test 1', 'kind' => TestKind::Mock, 'duration_minutes' => 20, 'is_published' => true]);
        $practice->questions()->attach($bank->take(3)->mapWithKeys(fn ($q, $i) => [$q->id => ['position' => $i]]));
        $mock->questions()->attach($bank->mapWithKeys(fn ($q, $i) => [$q->id => ['position' => $i]]));

        $physics = Subject::create(['course_id' => $imu->id, 'title' => 'Physics', 'description' => 'Mechanics, fluids, heat and electricity.']);
        $chemistry = Subject::create(['course_id' => $imu->id, 'title' => 'Chemistry', 'description' => 'Physical, inorganic and organic chemistry.']);
        foreach ([[$physics, 0], [$chemistry, 1], [$mock, 2]] as [$item, $position]) {
            CurriculumItem::create(['course_id' => $imu->id, 'item_type' => $item->getMorphClass(), 'item_id' => $item->id, 'position' => $position]);
        }

        $chapter = $physics->chapters()->create(['title' => 'Units, Dimensions & Errors', 'position' => 0]);
        $video = Video::create(['provider' => 'youtube', 'provider_ref' => 'dQw4w9WgXcQ', 'duration_seconds' => 2292]);
        $note = Note::create(['body' => '<p>Dimensional formula of force: $[MLT^{-2}]$</p>']);
        foreach ([
            ['video', 'Introduction to SI Units', $video],
            ['note', 'Dimensional Analysis — Quick Summary', $note],
            ['practice_test', 'Practice Test: Units & Dimensions', $practice],
        ] as $position => [$type, $title, $material]) {
            ChapterContent::create(['chapter_id' => $chapter->id, 'type' => $type, 'title' => $title, 'contentable_type' => $material->getMorphClass(), 'contentable_id' => $material->id, 'position' => $position]);
        }
        $chemistry->chapters()->create(['title' => 'Mole Concept', 'position' => 0]);

        // ---- Course 2 ----
        $sponsor = Course::create(['title' => 'Company Sponsorship Exam Prep', 'slug' => 'sponsorship-prep', 'description' => 'Aptitude, English and ship knowledge for sponsorship tests.']);

        $enroll = app(EnrollmentService::class);
        $enroll->assign($students[0], $imu, $admin);
        $enroll->assign($students[0], $sponsor, $admin);
        $enroll->assign($students[1], $imu, $admin);
    }
}
