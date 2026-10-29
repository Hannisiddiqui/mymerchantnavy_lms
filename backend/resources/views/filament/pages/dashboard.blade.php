<x-filament-panels::page>
    <style>
        .mmn-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        @media (min-width: 1024px) {
            .mmn-stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        .mmn-stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px 18px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dark .mmn-stat-card {
            background: #111827;
            border-color: #1f2937;
        }
        .mmn-stat-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08);
        }
        .mmn-stat-card.is-active {
            border: 2px solid #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.18);
        }
        .mmn-stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .mmn-stat-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }
        .dark .mmn-stat-title {
            color: #9ca3af;
        }
        .mmn-stat-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
            background: #2563eb;
            color: #ffffff;
        }
        .mmn-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #111827;
            margin: 6px 0 2px;
            line-height: 1.1;
        }
        .dark .mmn-stat-value {
            color: #ffffff;
        }
        .mmn-stat-desc {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.3;
        }
        .dark .mmn-stat-desc {
            color: #9ca3af;
        }

        .mmn-filter-bar {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .dark .mmn-filter-bar {
            background: #111827;
            border-color: #1f2937;
        }
        .mmn-filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .mmn-filter-left {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        .mmn-filter-label {
            font-size: 12px;
            font-weight: 700;
            color: #374151;
        }
        .dark .mmn-filter-label {
            color: #d1d5db;
        }
        .mmn-filter-select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            background: #f9fafb;
            color: #111827;
            cursor: pointer;
        }
        .dark .mmn-filter-select {
            border-color: #374151;
            background: #1f2937;
            color: #f9fafb;
        }
        .mmn-filter-input {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            background: #f9fafb;
            color: #111827;
            width: 100%;
            max-width: 240px;
        }
        .dark .mmn-filter-input {
            border-color: #374151;
            background: #1f2937;
            color: #f9fafb;
        }
        .mmn-filter-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f3f4f6;
            font-size: 12px;
        }
        .dark .mmn-filter-footer {
            border-color: #1f2937;
        }
        .mmn-filter-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .dark .mmn-filter-badge {
            background: rgba(30, 58, 138, 0.3);
            color: #93c5fd;
            border-color: #1e40af;
        }
        .mmn-clear-btn {
            font-size: 12px;
            font-weight: 700;
            color: #e11d48;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }
        .mmn-clear-btn:hover {
            text-decoration: underline;
        }
    </style>

    {{-- 1. Interactive Summary Cards --}}
    <div class="mmn-stats-grid">
        @foreach ($this->summaryStats as $key => $stat)
            @php
                $isActive = $this->activeFilter === $stat['key'];
            @endphp
            <div
                wire:click="setFilter('{{ $stat['key'] }}')"
                class="mmn-stat-card {{ $isActive ? 'is-active' : '' }}"
            >
                <div class="mmn-stat-header">
                    <span class="mmn-stat-title">
                        {{ $stat['label'] }}
                    </span>
                    @if ($isActive)
                        <span class="mmn-stat-badge">
                            Active Filter
                        </span>
                    @endif
                </div>

                <div class="mmn-stat-value">
                    {{ number_format($stat['count']) }}
                </div>

                <div class="mmn-stat-desc">
                    {{ $stat['desc'] }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- 2. Dedicated Filter Control Bar --}}
    <div class="mmn-filter-bar">
        <div class="mmn-filter-row">
            {{-- Left: Filter Selector and Course Selector --}}
            <div class="mmn-filter-left">
                <span class="mmn-filter-label">Filter Dataset:</span>

                <select
                    wire:model.live="activeFilter"
                    class="mmn-filter-select"
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
                    class="mmn-filter-select"
                >
                    <option value="">All Courses</option>
                    @foreach ($this->coursesList as $cid => $ctitle)
                        <option value="{{ $cid }}">{{ $ctitle }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Right: Live Search Box --}}
            <div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Search student, email, phone..."
                    class="mmn-filter-input"
                />
            </div>
        </div>

        {{-- Persistent Active Filter Badge and Reset --}}
        <div class="mmn-filter-footer">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="color: #6b7280;">Current View:</span>
                <span class="mmn-filter-badge">
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
                    class="mmn-clear-btn"
                >
                    ✕ Clear Filter
                </button>
            @endif
        </div>
    </div>

    {{-- 3. Filtered Students Table (Activity Tracker Removed As Requested) --}}
    <div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
