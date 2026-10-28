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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Selected Course</span>
                <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ $this->selectedCourse->title }}</p>
                <span class="text-xs text-brand-600 dark:text-brand-400 mt-1 inline-block">Active in Catalog</span>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Current Enrolled Students</span>
                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $this->selectedCourse->enrollments_count ?? 0 }}</p>
                <span class="text-xs text-gray-500 mt-1 inline-block">Students holding this course</span>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/30 dark:bg-blue-950/20 p-4 shadow-sm">
                <span class="text-xs font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wider">Sample Expiry (If Purchased Today)</span>
                <p class="text-sm font-semibold text-gray-900 dark:text-white mt-1">{{ $this->calculatedSampleExpiry }}</p>
                <span class="text-xs text-gray-500 mt-1 inline-block">Calculated using true calendar arithmetic</span>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
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

