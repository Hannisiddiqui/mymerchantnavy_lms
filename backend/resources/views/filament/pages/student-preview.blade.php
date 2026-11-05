<x-filament-panels::page>
    <x-filament::callout color="info" icon="heroicon-o-eye" heading="See your course the way a student does">
        <x-slot name="description">
            Pick a course and the student site opens, signed in as a private preview student who has only that course. It is never
            counted in students, purchases, test results or rankings. Anything you do there (opening lessons, taking tests) stays on that preview account.
        </x-slot>
    </x-filament::callout>

    <div class="mmn-card-grid">
        @forelse ($this->courses() as $course)
            <div class="mmn-card">
                <div class="mmn-stat-label">Course</div>
                <div class="mmn-stat-value" style="font-size:1.0625rem">{{ $course->title }}</div>
                <div style="margin-top:1rem">
                    <x-filament::button wire:click="open({{ $course->id }})" wire:loading.attr="disabled" icon="heroicon-o-arrow-top-right-on-square" size="sm">
                        Open as student
                    </x-filament::button>
                </div>
            </div>
        @empty
            <div class="mmn-card"><div class="mmn-muted">No active courses yet. Create a course, switch it to Active, and it appears here.</div></div>
        @endforelse
    </div>
</x-filament-panels::page>
