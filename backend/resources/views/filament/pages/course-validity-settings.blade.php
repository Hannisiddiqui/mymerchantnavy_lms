<x-filament-panels::page>
    <style>
        .mmn-validity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        .mmn-validity-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dark .mmn-validity-card {
            background: #111827;
            border-color: #1f2937;
        }
        .mmn-validity-card.highlight {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        .dark .mmn-validity-card.highlight {
            background: rgba(6, 78, 59, 0.2);
            border-color: #065f46;
        }
        .mmn-val-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }
        .dark .mmn-val-label {
            color: #9ca3af;
        }
        .mmn-val-title {
            font-size: 20px;
            font-weight: 800;
            color: #111827;
            margin: 6px 0 2px;
        }
        .dark .mmn-val-title {
            color: #ffffff;
        }
        .mmn-val-sub {
            font-size: 12px;
            font-weight: 600;
            color: #2563eb;
        }
        .dark .mmn-val-sub {
            color: #60a5fa;
        }
    </style>

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
        <div class="mmn-validity-grid">
            <div class="mmn-validity-card">
                <span class="mmn-val-label">Selected Course</span>
                <p class="mmn-val-title">{{ $this->selectedCourse->title }}</p>
                <span class="mmn-val-sub">Active in Catalog</span>
            </div>

            <div class="mmn-validity-card">
                <span class="mmn-val-label">Current Enrolled Students</span>
                <p class="mmn-val-title" style="font-size: 26px;">{{ $this->selectedCourse->enrollments_count ?? 0 }}</p>
                <span class="mmn-val-label" style="font-size: 12px; text-transform: none;">Students holding this course</span>
            </div>

            <div class="mmn-validity-card highlight">
                <span class="mmn-val-label" style="color: #15803d;">Sample Expiry (If Purchased Today)</span>
                <p class="mmn-val-title" style="font-size: 16px; color: #166534;">{{ $this->calculatedSampleExpiry }}</p>
                <span class="mmn-val-label" style="font-size: 12px; text-transform: none; color: #15803d;">Calculated using true calendar arithmetic</span>
            </div>
        </div>
    @endif

    <form wire:submit="save" style="margin-top: 24px;">
        {{ $this->form }}

        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 24px;">
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
