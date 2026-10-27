<x-filament-panels::page>
    <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-4 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 text-rose-600 dark:text-rose-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <div>
                <p class="font-semibold text-rose-900 dark:text-rose-200">Highest Privilege Zone — Super Admin Student Account Removal</p>
                <p class="mt-1 text-xs text-rose-700 dark:text-rose-400">
                    Only the Super Admin (<code class="font-mono font-semibold">support.mymerchantnavy@gmail.com</code>) has authority to permanently delete student accounts. Deleting an account will wipe all test attempts, scores, personal notes, and revoke all active device sessions. Every deletion is strictly audited with mandatory reason tracking in the <a href="{{ url(config('lms.admin_domain') ? '' : '/admin') }}/website-activity" class="underline font-semibold hover:text-rose-950">Website Activity Log</a>.
                </p>
            </div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
