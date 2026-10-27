<x-filament-panels::page>
    <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 text-blue-600 dark:text-blue-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
                </svg>
            </div>
            <div>
                <p class="font-semibold text-blue-900 dark:text-blue-200">Super Admin Security Controls</p>
                <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">
                    These session limits are strictly controlled by the Super Admin (<code class="font-mono font-semibold">support.mymerchantnavy@gmail.com</code>). Changes immediately apply across all student login flows, preventing account sharing and unauthorized access.
                </p>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Student Device & Session Restrictions</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Govern how many simultaneous devices each student may use to access courses and tests.</p>

            <div class="mt-4 max-w-xl">
                <label for="student_max_devices" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Maximum Concurrent Devices Per Student
                </label>
                <div class="mt-1">
                    <input
                        type="number"
                        id="student_max_devices"
                        wire:model="student_max_devices"
                        min="0"
                        max="10"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        required
                    />
                </div>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    <strong>0 = Unlimited devices.</strong> Set to <strong>1</strong> for strict single-device login, or <strong>2</strong> for phone + laptop. When a student logs in on a new device beyond this limit, their oldest active device is automatically signed out.
                </p>
                @error('student_max_devices')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Administrator Panel Session Security</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Set timeout policies for administrators and staff logged into this panel.</p>

            <div class="mt-4 max-w-xl">
                <label for="admin_session_lifetime_minutes" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Admin Session Lifetime (Minutes)
                </label>
                <div class="mt-1">
                    <input
                        type="number"
                        id="admin_session_lifetime_minutes"
                        wire:model="admin_session_lifetime_minutes"
                        min="15"
                        max="1440"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        required
                    />
                </div>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    Minutes of inactivity before an admin is logged out (e.g. 120 for 2 hours, 480 for 8 hours).
                </p>
                @error('admin_session_lifetime_minutes')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex items-center gap-3">
            <x-filament::button type="submit" size="lg">
                Save Session Limits
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
