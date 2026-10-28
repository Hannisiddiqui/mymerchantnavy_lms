<x-filament-panels::page>
    <x-filament::callout
        color="info"
        icon="heroicon-o-lock-closed"
        heading="Super Admin Security Policy Controls"
    >
        <x-slot name="description">
            These session and concurrency controls are strictly managed by the Super Admin (<code class="font-mono font-semibold">support.mymerchantnavy@gmail.com</code>). 
            Configured limits enforce account security, preventing credential sharing and multi-tab exam exploitation across all student portals.
        </x-slot>
    </x-filament::callout>

    <form wire:submit="save" style="margin-top: 1.5rem;">
        {{ $this->form }}

        <div class="mmn-btn-row">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check">
                Save Session Policies
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
