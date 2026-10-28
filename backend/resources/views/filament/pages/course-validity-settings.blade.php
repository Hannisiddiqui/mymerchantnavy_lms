<x-filament-panels::page>
    <x-filament::callout
        color="info"
        icon="heroicon-o-calendar-days"
        heading="Automatic Calendar-Based Validity Calculation"
    >
        <x-slot name="description">
            Course validity is calculated using true calendar month/year arithmetic from each student's confirmed purchase date.
            When a student purchases a 6-month course on 1 January 2027, access expires exactly on 1 July 2027.
            Once expired, access is blocked across all student portal endpoints while preserving their historical test attempts and notes.
        </x-slot>
    </x-filament::callout>

    @if ($this->selectedCourse)
        <div class="mmn-card-grid">
            <div class="mmn-card">
                <span class="mmn-stat-label">Selected Course</span>
                <p class="mmn-stat-value" style="font-size: 1.25rem;">{{ $this->selectedCourse->title }}</p>
                <span class="mmn-stat-sub" style="color: #2563eb; font-weight: 600;">Active in Catalog</span>
            </div>

            <div class="mmn-card">
                <span class="mmn-stat-label">Current Enrolled Students</span>
                <p class="mmn-stat-value">{{ $this->selectedCourse->enrollments_count ?? 0 }}</p>
                <span class="mmn-stat-sub">Students holding this course</span>
            </div>

            <div class="mmn-card mmn-card-blue">
                <span class="mmn-stat-label" style="color: #1d4ed8;">Sample Expiry (If Purchased Today)</span>
                <p class="mmn-stat-value" style="font-size: 1.125rem;">{{ $this->calculatedSampleExpiry }}</p>
                <span class="mmn-stat-sub">Calculated using true calendar arithmetic</span>
            </div>
        </div>
    @endif

    <form wire:submit="save" style="margin-top: 1.5rem;">
        {{ $this->form }}

        <div class="mmn-btn-row">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check">
                Save Validity Settings
            </x-filament::button>

            @if ($this->selectedCourse && ($this->selectedCourse->enrollments_count ?? 0) > 0)
                <x-filament::button
                    type="button"
                    color="gray"
                    size="lg"
                    icon="heroicon-o-arrow-path"
                    wire:click="recalculateExistingEnrollments"
                    wire:confirm="Are you sure you want to recalculate the expiry timestamps for all {{ $this->selectedCourse->enrollments_count }} existing students of '{{ $this->selectedCourse->title }}' based on their recorded purchase date? This will update their active/expired status accordingly."
                >
                    Recalculate {{ $this->selectedCourse->enrollments_count }} Existing Enrollments
                </x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>

