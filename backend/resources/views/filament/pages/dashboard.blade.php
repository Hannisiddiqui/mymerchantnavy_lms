<x-filament-panels::page>
    {{-- 1. Interactive Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ($this->summaryStats as $key => $stat)
            @php
                $isActive = $this->activeFilter === $stat['key'];
            @endphp
            <div
                wire:click="setFilter('{{ $stat['key'] }}')"
                class="cursor-pointer rounded-xl p-4 transition-all hover:shadow-md bg-white dark:bg-gray-900 {{ $isActive ? 'border-2 border-primary-600 dark:border-primary-500 shadow-md ring-2 ring-primary-500/20' : 'border border-gray-200 dark:border-gray-800 shadow-sm' }}"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ $stat['label'] }}
                    </span>
                    @if ($isActive)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary-600 text-white">
                            Active Filter
                        </span>
                    @endif
                </div>

                <div class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">
                    {{ number_format($stat['count']) }}
                </div>

                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1.5">
                    <span>{{ $stat['desc'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- 2. Dedicated Filter Control Bar --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 mb-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            {{-- Left: Filter Selector and Active Indicator --}}
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Filter Dataset:
                </label>

                <select
                    wire:model.live="activeFilter"
                    class="border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-xs bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500"
                >
                    <option value="all">All Registered Students</option>
                    <option value="paid">Paid Students (Has Purchase)</option>
                    <option value="free">Free Course Students</option>
                    <option value="no_course">Students Without a Course</option>
                    <option value="active_enrollments">Active Course Enrollments</option>
                    <option value="expired_enrollments">Expired Enrollments</option>
                    <option value="today">Registered Today</option>
                </select>

                <select
                    wire:model.live="selectedCourse"
                    class="border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-xs bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500"
                >
                    <option value="">All Courses</option>
                    @foreach ($this->coursesList as $cid => $ctitle)
                        <option value="{{ $cid }}">{{ $ctitle }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Right: Live Search Box --}}
            <div class="flex items-center gap-2 flex-1 max-w-xs min-w-[200px]">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Search student, email, phone..."
                    class="w-full border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-xs bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 placeholder-gray-400 dark:placeholder-gray-500"
                />
            </div>
        </div>

        {{-- Persistent Active Filter Badge and Reset --}}
        <div class="flex items-center justify-between gap-3 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2 text-xs">
                <span class="text-gray-500 dark:text-gray-400">Current View:</span>
                <span class="font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/40 px-2.5 py-0.5 rounded-full border border-primary-200 dark:border-primary-900">
                    {{ $this->activeFilterLabel }}
                    @if ($this->selectedCourse && isset($this->coursesList[$this->selectedCourse]))
                        · Course: {{ $this->coursesList[$this->selectedCourse] }}
                    @endif
                    @if (filled($this->searchQuery))
                        · Search: "{{ $this->searchQuery }}"
                    @endif
                </span>
            </div>

            @if ($this->activeFilter !== 'all' || $this->selectedCourse || filled($this->searchQuery))
                <button
                    type="button"
                    wire:click="clearFilter"
                    class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-1"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Clear Filter
                </button>
            @endif
        </div>
    </div>

    {{-- 3. Filtered Students Table --}}
    <div class="mb-8">
        {{ $this->table }}
    </div>

    {{-- 4. 24h Activity & Security Summary --}}
    <div class="mt-8">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3">
            Website Activity & Security Log (Last 24 Hours)
        </h3>
        @livewire(\App\Filament\Widgets\WebsiteActivityStats::class)
    </div>
</x-filament-panels::page>
