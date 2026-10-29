<div class="space-y-6 text-sm">
    {{-- Student Profile Card --}}
    <div class="bg-gray-50 dark:bg-gray-900/60 rounded-xl p-4 border border-gray-200 dark:border-gray-800">
        <div class="flex items-start justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-full bg-brand-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                    {{ strtoupper(substr($record->name ?: $record->email, 0, 1)) }}
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ $record->name ?: 'Profile Incomplete' }}</h3>
                    <p class="text-gray-500 font-mono text-xs">{{ $record->email }}</p>
                    @if ($record->phone)
                        <p class="text-gray-500 text-xs mt-0.5">{{ $record->phone }}</p>
                    @endif
                </div>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $record->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }}">
                    {{ $record->is_active ? 'Account Active' : 'Account Inactive' }}
                </span>
                <p class="text-xs text-gray-500 font-mono mt-1">Reg: {{ $record->created_at->format('d M Y, h:i:s A') }}</p>
                <div class="mt-1">
                    @if ($record->enrollments->contains('purchase_type', 'paid'))
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400">
                            Paid Student
                        </span>
                    @elseif ($record->enrollments->contains('purchase_type', 'free'))
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-400">
                            Free Course Student
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-400">
                            No Course Assigned
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 text-xs">
            <div>
                <span class="text-gray-500 block">Target Exam</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $record->target_exam ?: '—' }}</span>
            </div>
            <div>
                <span class="text-gray-500 block">Attempt Year</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $record->attempt_year ?: '—' }}</span>
            </div>
            <div>
                <span class="text-gray-500 block">Email Verified</span>
                <span class="font-semibold {{ $record->email_verified_at ? 'text-green-600 dark:text-green-400' : 'text-amber-500' }}">
                    {{ $record->email_verified_at ? 'Verified' : 'Pending Verification' }}
                </span>
            </div>
            <div>
                <span class="text-gray-500 block">Total Attempts</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $record->attempts()->count() }} tests</span>
            </div>
        </div>
    </div>

    {{-- Course Purchase & Validity History --}}
    <div>
        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Purchased Courses & Expiry</h4>
        @php
            $enrollments = $record->enrollments()->with('course')->orderBy('created_at', 'desc')->get();
        @endphp

        @if ($enrollments->isEmpty())
            <div class="p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 rounded-xl text-center text-amber-700 dark:text-amber-400 text-xs">
                No courses assigned or purchased yet. Click "Assign course" to grant access.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-semibold">
                        <tr>
                            <th class="p-2.5">Course</th>
                            <th class="p-2.5">Type</th>
                            <th class="p-2.5">Purchase Date</th>
                            <th class="p-2.5">Expiry Date</th>
                            <th class="p-2.5">Remaining</th>
                            <th class="p-2.5">Order / Payment</th>
                            <th class="p-2.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        @foreach ($enrollments as $enr)
                            <tr>
                                <td class="p-2.5 font-bold text-gray-900 dark:text-white">{{ $enr->course?->title ?? 'Untitled Course' }}</td>
                                <td class="p-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium {{ $enr->purchase_type === 'paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400' : ($enr->purchase_type === 'free' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300') }}">
                                        {{ ucfirst($enr->purchase_type ?: 'Assigned') }}
                                    </span>
                                </td>
                                <td class="p-2.5 text-gray-600 dark:text-gray-400">
                                    {{ $enr->purchase_date ? $enr->purchase_date->format('d M Y') : ($enr->assigned_at ? $enr->assigned_at->format('d M Y') : '—') }}
                                </td>
                                <td class="p-2.5 font-mono text-gray-800 dark:text-gray-200">
                                    {{ $enr->expires_at ? $enr->expires_at->format('d M Y, H:i') : 'Lifetime' }}
                                </td>
                                <td class="p-2.5">
                                    <span class="{{ $enr->isExpired() ? 'text-red-500 font-semibold' : 'text-emerald-600 dark:text-emerald-400 font-medium' }}">
                                        {{ $enr->remainingDuration() }}
                                    </span>
                                </td>
                                <td class="p-2.5 font-mono text-gray-500 text-[11px]">
                                    @if ($enr->order_id)
                                        <div>Ord: #{{ $enr->order_id }}</div>
                                    @endif
                                    @if ($enr->amount)
                                        <div class="text-gray-700 dark:text-gray-300 font-semibold">₹{{ number_format($enr->amount, 2) }}</div>
                                    @endif
                                    @if (! $enr->order_id && ! $enr->amount)
                                        —
                                    @endif
                                </td>
                                <td class="p-2.5">
                                    @if ($enr->isExpired())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400">
                                            Expired
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400">
                                            Active
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Recent Activity & Tests --}}
    <div>
        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Recent Test Attempts</h4>
        @php
            $recentAttempts = $record->attempts()->with('test')->whereNotNull('submitted_at')->orderBy('submitted_at', 'desc')->take(5)->get();
        @endphp

        @if ($recentAttempts->isEmpty())
            <div class="p-3 bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-800 rounded-xl text-center text-gray-500 text-xs">
                No tests completed yet.
            </div>
        @else
            <div class="space-y-2">
                @foreach ($recentAttempts as $att)
                    <div class="flex items-center justify-between p-2.5 bg-white dark:bg-gray-800/60 border border-gray-200 dark:border-gray-800 rounded-lg text-xs">
                        <div>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $att->test->title }}</span>
                            <span class="text-gray-400 block text-[11px]">{{ $att->submitted_at->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-sm text-brand-600 dark:text-brand-400">{{ $att->score }}</span>
                            <span class="text-gray-400 text-xs block">marks</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

