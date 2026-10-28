<x-filament-panels::page>
    {{-- 1. Interactive Summary Cards --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        @foreach ($this->summaryStats as $key => $stat)
            @php
                $isActive = $this->activeFilter === $stat['key'];
            @endphp
            <div
                wire:click="setFilter('{{ $stat['key'] }}')"
                style="cursor: pointer; background-color: #ffffff; border-radius: 0.75rem; padding: 1.125rem; transition: all 0.2s ease; border: {{ $isActive ? '2px solid #2563eb' : '1px solid #e2e8f0' }}; box-shadow: {{ $isActive ? '0 4px 12px rgba(37, 99, 235, 0.15)' : '0 1px 3px rgba(0,0,0,0.05)' }};"
                class="hover:shadow-md dark:bg-gray-900 dark:border-gray-800"
            >
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                    <span style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;" class="dark:text-gray-400">
                        {{ $stat['label'] }}
                    </span>
                    @if ($isActive)
                        <span style="font-size: 0.65rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 9999px; background-color: #2563eb; color: #ffffff;">
                            Active Filter
                        </span>
                    @endif
                </div>

                <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-top: 0.35rem;" class="dark:text-white">
                    {{ number_format($stat['count']) }}
                </div>

                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.35rem;" class="dark:text-gray-400">
                    <span>{{ $stat['desc'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- 2. Dedicated Filter Control Bar --}}
    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem;" class="dark:bg-gray-900 dark:border-gray-800 shadow-sm">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
            {{-- Left: Filter Selector and Active Indicator --}}
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                <label style="font-size: 0.8125rem; font-weight: 600; color: #334155;" class="dark:text-gray-300">
                    Filter Dataset:
                </label>

                <select
                    wire:model.live="activeFilter"
                    style="border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.4rem 0.75rem; font-size: 0.8125rem; background-color: #f8fafc; color: #0f172a;"
                    class="dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200"
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
                    style="border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.4rem 0.75rem; font-size: 0.8125rem; background-color: #f8fafc; color: #0f172a;"
                    class="dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200"
                >
                    <option value="">All Courses</option>
                    @foreach ($this->coursesList as $cid => $ctitle)
                        <option value="{{ $cid }}">{{ $ctitle }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Right: Live Search Box --}}
            <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; max-width: 320px; min-width: 200px;">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Search student, email, phone..."
                    style="width: 100%; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.4rem 0.75rem; font-size: 0.8125rem; background-color: #f8fafc; color: #0f172a;"
                    class="dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200"
                />
            </div>
        </div>

        {{-- Persistent Active Filter Badge and Reset --}}
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9;" class="dark:border-gray-800">
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem;">
                <span style="color: #64748b;">Current View:</span>
                <span style="font-weight: 700; color: #2563eb; background-color: #eff6ff; padding: 0.2rem 0.6rem; border-radius: 9999px; border: 1px solid #dbeafe;" class="dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-900">
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
                    style="font-size: 0.75rem; font-weight: 600; color: #dc2626; background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;"
                    class="hover:underline"
                >
                    <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Clear Filter
                </button>
            @endif
        </div>
    </div>

    {{-- 3. Filtered Students Table --}}
    <div style="margin-bottom: 2rem;">
        {{ $this->table }}
    </div>

    {{-- 4. 24h Activity & Security Summary --}}
    <div style="margin-top: 2rem;">
        <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;" class="dark:text-white">
            Website Activity & Security Log (Last 24 Hours)
        </h3>
        @livewire(\App\Filament\Widgets\WebsiteActivityStats::class)
    </div>
</x-filament-panels::page>
