<x-filament-panels::page>
    <x-filament::callout
        color="info"
        icon="heroicon-o-eye"
        heading="Student LMS Navigation & Page Visibility Control"
    >
        <x-slot name="description">
            Choose which pages and modules are active and accessible on the student portal. 
            Hidden pages are removed from the student sidebar menu in real time.
        </x-slot>
    </x-filament::callout>

    <form wire:submit="save" style="margin-top: 1.5rem;">
        {{ $this->form }}

        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check">
                Save Page Visibility
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

