<?php

namespace App\Filament\Pages;

use App\Filament\Support\AssignCourseAction;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\TestAttempt;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Supercharged LMS Admin Dashboard with interactive summary cards,
 * persistent filtering, dedicated filter controls, and real-time student management.
 */
class Dashboard extends BaseDashboard implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.pages.dashboard';

    public string $activeFilter = 'all';

    public ?int $selectedCourse = null;

    public string $searchQuery = '';

    public function getWidgets(): array
    {
        return [];
    }

    public function setFilter(string $filter): void
    {
        $this->activeFilter = $filter;
        $this->resetTable();
    }

    public function clearFilter(): void
    {
        $this->activeFilter = 'all';
        $this->selectedCourse = null;
        $this->searchQuery = '';
        $this->resetTable();
    }

    public function updatedSelectedCourse(): void
    {
        $this->resetTable();
    }

    public function updatedSearchQuery(): void
    {
        $this->resetTable();
    }

    public function getSummaryStatsProperty(): array
    {
        $hasExpiresAt = Schema::hasColumn('enrollments', 'expires_at');
        $hasPurchaseType = Schema::hasColumn('enrollments', 'purchase_type');

        $totalStudents = User::count();
        $paidStudents = $hasPurchaseType ? User::withPaidEnrollment()->count() : 0;
        $freeStudents = $hasPurchaseType ? User::withFreeEnrollment()->count() : 0;
        $studentsWithoutCourse = User::withoutCourses()->count();

        $activeEnrollments = $hasExpiresAt ? Enrollment::active()->count() : Enrollment::count();
        $expiredEnrollments = $hasExpiresAt ? Enrollment::expired()->count() : 0;
        $testsToday = TestAttempt::whereDate('submitted_at', today())->count();

        return [
            'total_students' => [
                'key' => 'all',
                'label' => 'Total Students',
                'count' => $totalStudents,
                'desc' => User::whereDate('created_at', today())->count() . ' registered today',
                'color' => 'blue',
            ],
            'paid_students' => [
                'key' => 'paid',
                'label' => 'Paid Students',
                'count' => $paidStudents,
                'desc' => ($totalStudents > 0 ? round(($paidStudents / $totalStudents) * 100) : 0) . '% of total students',
                'color' => 'emerald',
            ],
            'students_without_course' => [
                'key' => 'no_course',
                'label' => 'Students Without Course',
                'count' => $studentsWithoutCourse,
                'desc' => 'Awaiting course assignment',
                'color' => 'amber',
            ],
            'free_students' => [
                'key' => 'free',
                'label' => 'Free Course Students',
                'count' => $freeStudents,
                'desc' => 'Enrolled in free courses',
                'color' => 'sky',
            ],
            'active_enrollments' => [
                'key' => 'active_enrollments',
                'label' => 'Active Enrollments',
                'count' => $activeEnrollments,
                'desc' => 'Currently valid course access',
                'color' => 'green',
            ],
            'expired_enrollments' => [
                'key' => 'expired_enrollments',
                'label' => 'Expired Enrollments',
                'count' => $expiredEnrollments,
                'desc' => 'Completed course validity',
                'color' => 'rose',
            ],
            'tests_today' => [
                'key' => 'today',
                'label' => 'Tests Taken Today',
                'count' => $testsToday,
                'desc' => TestAttempt::whereNotNull('submitted_at')->count() . ' total test attempts',
                'color' => 'indigo',
            ],
        ];
    }

    public function getActiveFilterLabelProperty(): string
    {
        return match ($this->activeFilter) {
            'paid' => 'Paid Students (Has Paid Purchase)',
            'free' => 'Free Course Students',
            'no_course' => 'Students Without a Course',
            'active_enrollments' => 'Active Enrollments (Valid Access)',
            'expired_enrollments' => 'Expired Enrollments',
            'today' => 'Registered Today',
            default => 'All Registered Students',
        };
    }

    public function getCoursesListProperty(): array
    {
        return Course::orderBy('title')->pluck('title', 'id')->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $hasPurchaseType = Schema::hasColumn('enrollments', 'purchase_type');
                $hasExpiresAt = Schema::hasColumn('enrollments', 'expires_at');

                $query = User::query()->with(['enrollments.course', 'attempts']);

                // Card and Quick Filter
                $query = match ($this->activeFilter) {
                    'paid' => $hasPurchaseType ? $query->withPaidEnrollment() : $query,
                    'free' => $hasPurchaseType ? $query->withFreeEnrollment() : $query,
                    'no_course' => $query->withoutCourses(),
                    'active_enrollments' => $hasExpiresAt ? $query->withActiveEnrollment() : $query,
                    'expired_enrollments' => $hasExpiresAt ? $query->withExpiredEnrollment() : $query,
                    'today' => $query->whereDate('created_at', today()),
                    default => $query,
                };

                // Course Filter
                if ($this->selectedCourse) {
                    $query->whereHas('enrollments', fn ($q) => $q->where('course_id', $this->selectedCourse));
                }

                // Search query
                if (filled($this->searchQuery)) {
                    $search = trim($this->searchQuery);
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                }

                return $query;
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Student')
                    ->searchable(['name', 'email', 'phone'])
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email . ($record->phone ? " • {$record->phone}" : ''))
                    ->formatStateUsing(fn ($state, User $record) => $state ?: $record->email),

                TextColumn::make('purchase_status')
                    ->label('Purchase Type')
                    ->badge()
                    ->state(function (User $record) {
                        $enrollments = $record->enrollments;
                        if ($enrollments->isEmpty()) {
                            return 'No Course';
                        }
                        if ($enrollments->contains('purchase_type', 'paid')) {
                            return 'Paid Course';
                        }
                        if ($enrollments->contains('purchase_type', 'free')) {
                            return 'Free Course';
                        }

                        return 'Assigned';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Paid Course' => 'success',
                        'Free Course' => 'info',
                        'Assigned' => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('enrolled_courses')
                    ->label('Course Access & Expiry')
                    ->state(function (User $record) {
                        $enrollments = $record->enrollments;
                        if ($enrollments->isEmpty()) {
                            return 'None';
                        }

                        return $enrollments->map(function (Enrollment $enr) {
                            $title = $enr->course?->title ?? 'Course';
                            $status = $enr->isExpired() ? 'Expired' : ($enr->expires_at ? 'Exp: ' . $enr->expires_at->format('d M y') : 'Active');

                            return "{$title} ({$status})";
                        })->implode("\n");
                    })
                    ->wrap()
                    ->badge()
                    ->color(fn ($state) => str_contains((string) $state, 'Expired') ? 'danger' : 'primary'),

                TextColumn::make('target_exam')
                    ->label('Target Exam')
                    ->toggleable()
                    ->placeholder('—')
                    ->description(fn (User $record) => $record->attempt_year ? "Year: {$record->attempt_year}" : null),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Registered Date & Time')
                    ->dateTime('d M Y, h:i:s A')
                    ->description(fn (User $record) => $record->created_at?->diffForHumans())
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalHeading(fn (User $record) => 'Student Overview: ' . ($record->name ?: $record->email))
                    ->modalContent(fn (User $record) => view('filament.resources.student-details', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('3xl'),

                AssignCourseAction::make(fn (Action $action) => $action->getRecord()),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No students found')
            ->emptyStateDescription('Try clearing your active filters or searching with a different term.');
    }
}
