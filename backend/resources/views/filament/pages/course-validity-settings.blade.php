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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 my-4">
            <div class="rounded-xl p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Selected Course</span>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $this->selectedCourse->title }}</p>
                <span class="text-xs font-semibold text-primary-600 dark:text-primary-400 mt-2">Active in Catalog</span>
            </div>

            <div class="rounded-xl p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Current Enrolled Students</span>
                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $this->selectedCourse->enrollments_count ?? 0 }}</p>
                <span class="text-xs text-gray-500 dark:text-gray-400 mt-2">Students holding this course</span>
            </div>

            <div class="rounded-xl p-5 bg-primary-50/50 dark:bg-primary-950/30 border border-primary-200 dark:border-primary-800/60 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Sample Expiry (If Purchased Today)</span>
                <p class="text-lg font-bold text-primary-900 dark:text-primary-100 mt-1">{{ $this->calculatedSampleExpiry }}</p>
                <span class="text-xs text-primary-600 dark:text-primary-400 mt-2">Calculated using true calendar arithmetic</span>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="mt-6 space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3 pt-2">
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

