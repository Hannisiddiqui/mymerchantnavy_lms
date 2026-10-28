<x-filament-panels::page>
    <x-filament::callout
        color="danger"
        icon="heroicon-o-exclamation-triangle"
        heading="Highest Privilege Zone — Super Admin Student Account Removal"
    >
        <x-slot name="description">
            Only the Super Admin (<code class="font-mono font-semibold">support.mymerchantnavy@gmail.com</code>) has authority to permanently delete student accounts. Deleting an account will wipe all test attempts, scores, personal notes, and revoke all active device sessions. Every deletion is strictly audited with mandatory reason tracking in the <a href="{{ url(config('lms.admin_domain') ? '' : '/admin') }}/website-activity" class="underline font-semibold hover:text-rose-950">Website Activity Log</a>.
        </x-slot>
    </x-filament::callout>

    {{ $this->table }}
</x-filament-panels::page>
