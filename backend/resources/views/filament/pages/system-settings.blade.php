<x-filament-panels::page>
    <x-filament::callout
        color="info"
        icon="heroicon-o-shield-check"
        heading="System Communication & Audit Alert Preferences"
    >
        <x-slot name="description">
            Configure automated change notifications, staff alert recipients, and student support channels. 
            All modifications are recorded with timestamp, author, and IP tracking in the Website Activity Log.
        </x-slot>
    </x-filament::callout>

    <form wire:submit="save" class="mt-6 space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-4 mt-6 pt-5 border-t border-gray-200 dark:border-gray-800">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check">
                Save Notification Settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

