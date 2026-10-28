<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StudentResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\TestAttempt;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Interactive LMS Dashboard Summary Cards.
 * Every card is clickable and immediately filters the student management dataset.
 */
class PlatformStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $totalStudents = User::count();
        $paidStudents = User::withPaidEnrollment()->count();
        $freeStudents = User::withFreeEnrollment()->count();
        $studentsWithoutCourse = User::withoutCourses()->count();

        $activeEnrollments = Enrollment::active()->count();
        $expiredEnrollments = Enrollment::expired()->count();
        $testsToday = TestAttempt::whereDate('submitted_at', today())->count();

        return [
            Stat::make('Total Students', $totalStudents)
                ->description(User::whereDate('created_at', today())->count() . ' registered today')
                ->descriptionIcon('heroicon-m-user-plus')
                ->icon('heroicon-o-academic-cap')
                ->color('primary')
                ->url(StudentResource::getUrl('index')),

            Stat::make('Paid Students', $paidStudents)
                ->description(round($totalStudents > 0 ? ($paidStudents / $totalStudents) * 100 : 0) . '% of total students')
                ->descriptionIcon('heroicon-m-check-badge')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url(StudentResource::getUrl('index', ['tableFilters[purchase_status][value]' => 'paid'])),

            Stat::make('Students Without Course', $studentsWithoutCourse)
                ->description('Awaiting course assignment')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->url(StudentResource::getUrl('index', ['tableFilters[purchase_status][value]' => 'no_course'])),

            Stat::make('Free Course Students', $freeStudents)
                ->description('Enrolled in free courses')
                ->descriptionIcon('heroicon-m-gift')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->url(StudentResource::getUrl('index', ['tableFilters[purchase_status][value]' => 'free'])),

            Stat::make('Active Enrollments', $activeEnrollments)
                ->description('Currently valid course access')
                ->descriptionIcon('heroicon-m-shield-check')
                ->icon('heroicon-o-book-open')
                ->color('success')
                ->url(StudentResource::getUrl('index', ['tableFilters[enrollment_status][value]' => 'active'])),

            Stat::make('Expired Enrollments', $expiredEnrollments)
                ->description('Completed course validity')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->url(StudentResource::getUrl('index', ['tableFilters[enrollment_status][value]' => 'expired'])),

            Stat::make('Tests Taken Today', $testsToday)
                ->description(TestAttempt::whereNotNull('submitted_at')->count() . ' total test attempts')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->url(route('filament.admin.resources.test-results.index')),
        ];
    }
}
