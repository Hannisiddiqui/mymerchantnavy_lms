<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Enrollment;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

/**
 * Super Admin Course Validity Management
 * Configure automatic calendar-based validity rules from purchase date.
 */
class CourseValiditySettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Super Admin';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Course Validity & Access Rules';

    protected static ?string $navigationLabel = 'Course Validity';

    protected string $view = 'filament.pages.course-validity-settings';

    public ?array $data = [];

    public ?int $selectedCourseId = null;

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $firstCourse = Course::orderBy('title')->first();
        if ($firstCourse) {
            $this->loadCourse($firstCourse->id);
        }
    }

    public function loadCourse(?int $courseId): void
    {
        if (! $courseId) {
            return;
        }

        $course = Course::find($courseId);
        if (! $course) {
            return;
        }

        $this->selectedCourseId = $course->id;

        $this->data = [
            'course_id' => $course->id,
            'validity_type' => $course->validity_type ?: '6_months',
            'validity_duration_months' => $course->validity_duration_months ?: 6,
            'validity_duration_days' => $course->validity_duration_days,
            'validity_fixed_expiry_date' => $course->validity_fixed_expiry_date ? (is_string($course->validity_fixed_expiry_date) ? substr($course->validity_fixed_expiry_date, 0, 10) : $course->validity_fixed_expiry_date->format('Y-m-d')) : null,
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Select Course')
                    ->description('Choose which course to configure validity and access limits for.')
                    ->schema([
                        Select::make('course_id')
                            ->label('Course')
                            ->options(Course::orderBy('title')->pluck('title', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $old) {
                                if ($state && (int)$state !== (int)$this->selectedCourseId) {
                                    $this->loadCourse((int) $state);
                                }
                            }),
                    ]),

                Section::make('Validity Duration Rules')
                    ->description('Automatically calculate student expiry date from their confirmed purchase timestamp.')
                    ->schema([
                        Select::make('validity_type')
                            ->label('Course Validity Rule')
                            ->options([
                                '6_months' => '6 Calendar Months (Standard)',
                                '1_year' => '1 Calendar Year (Standard Annual)',
                                'custom' => 'Custom Duration / Fixed Expiry Date',
                                'lifetime' => 'Lifetime Access (No Expiration)',
                            ])
                            ->default('6_months')
                            ->required()
                            ->live(),

                        TextInput::make('validity_duration_months')
                            ->label('Custom Duration in Months')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->visible(fn ($get) => $get('validity_type') === 'custom')
                            ->helperText('Calendar months added to student purchase date (e.g. 3, 9, 24).'),

                        DatePicker::make('validity_fixed_expiry_date')
                            ->label('Or Fixed Expiry Date')
                            ->visible(fn ($get) => $get('validity_type') === 'custom')
                            ->helperText('Specific fixed calendar date when all students lose access regardless of purchase date.'),
                    ])->columns(2),
            ]);
    }

    public function save(): void
    {
        $formData = $this->form->getState();
        $courseId = $formData['course_id'] ?? $this->selectedCourseId;

        $course = Course::findOrFail($courseId);
        $oldType = $course->validity_type;

        $course->update([
            'validity_type' => $formData['validity_type'],
            'validity_duration_months' => $formData['validity_type'] === 'custom' ? ($formData['validity_duration_months'] ?? null) : ($formData['validity_type'] === '6_months' ? 6 : ($formData['validity_type'] === '1_year' ? 12 : null)),
            'validity_duration_days' => $formData['validity_duration_days'] ?? null,
            'validity_fixed_expiry_date' => $formData['validity_type'] === 'custom' ? ($formData['validity_fixed_expiry_date'] ?? null) : null,
        ]);

        ActivityLog::record(
            event: 'course.validity_updated',
            description: "Super Admin updated validity rule for '{$course->title}' from '{$oldType}' to '{$course->validity_type}'.",
            subject: $course,
            properties: [
                'course_id' => $course->id,
                'validity_type' => $course->validity_type,
                'validity_duration_months' => $course->validity_duration_months,
                'validity_fixed_expiry_date' => $course->validity_fixed_expiry_date,
            ],
            admin: auth('admin')->user()
        );

        Notification::make()
            ->title('Course Validity Saved')
            ->body("New purchases of '{$course->title}' will automatically calculate validity as configured.")
            ->success()
            ->send();
    }

    public function recalculateExistingEnrollments(): void
    {
        $course = Course::findOrFail($this->selectedCourseId);
        $enrollments = Enrollment::where('course_id', $course->id)->get();

        $updatedCount = 0;
        foreach ($enrollments as $enrollment) {
            $purchaseDate = $enrollment->purchase_date ?: $enrollment->assigned_at ?: $enrollment->created_at;
            $newExpiry = $course->calculateExpiry($purchaseDate);

            $enrollment->update([
                'purchase_date' => $purchaseDate,
                'expires_at' => $newExpiry,
                'status' => ($newExpiry && $newExpiry->isPast()) ? 'expired' : 'active',
            ]);
            $updatedCount++;
        }

        ActivityLog::record(
            event: 'course.enrollments_recalculated',
            description: "Super Admin recalculated expiry dates for {$updatedCount} student enrollments of '{$course->title}' based on rule '{$course->validity_type}'.",
            subject: $course,
            properties: [
                'course_id' => $course->id,
                'updated_count' => $updatedCount,
                'validity_type' => $course->validity_type,
            ],
            admin: auth('admin')->user()
        );

        Notification::make()
            ->title('Enrollments Recalculated')
            ->body("Successfully recalculated and updated expiry timestamps for {$updatedCount} enrolled students.")
            ->success()
            ->send();
    }

    public function getSelectedCourseProperty(): ?Course
    {
        return $this->selectedCourseId ? Course::withCount('enrollments')->find($this->selectedCourseId) : null;
    }

    public function getCalculatedSampleExpiryProperty(): string
    {
        if (! $this->selectedCourse) {
            return '—';
        }

        $now = now();
        $sample = $this->selectedCourse->calculateExpiry($now);

        return $sample ? $sample->format('d M Y, H:i (e)') . ' (' . $now->diffForHumans($sample, true) . ' access)' : 'Lifetime Access (No Expiry)';
    }
}

