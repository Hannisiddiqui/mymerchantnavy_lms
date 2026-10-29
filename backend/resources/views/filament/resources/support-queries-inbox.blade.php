<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header & Mode Switcher Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-gray-900 p-5 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                        <x-heroicon-o-chat-bubble-left-right class="w-6 h-6" />
                    </span>
                    Support & WhatsApp Communication Hub
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Manage student doubts, WhatsApp counseling requests, and email communications in one unified screen.
                </p>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto">
                {{-- View Mode Toggle --}}
                <div class="inline-flex rounded-xl bg-gray-100 dark:bg-gray-800 p-1 border border-gray-200 dark:border-gray-700 text-xs font-semibold">
                    <button
                        type="button"
                        wire:click="$set('viewMode', 'chat')"
                        class="px-3 py-1.5 rounded-lg transition {{ $viewMode === 'chat' ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}"
                    >
                        Chat Console
                    </button>
                    <button
                        type="button"
                        wire:click="$set('viewMode', 'table')"
                        class="px-3 py-1.5 rounded-lg transition {{ $viewMode === 'table' ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-xs font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}"
                    >
                        Data Table
                    </button>
                </div>

                {{-- Quick Test Button --}}
                <button
                    type="button"
                    wire:click="createTestQuery"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-xs transition active:scale-95"
                    title="Generate a sample student query to test replying"
                >
                    <x-heroicon-o-plus class="w-4 h-4" />
                    <span class="hidden sm:inline">Add Sample Query</span>
                </button>
            </div>
        </div>

        {{-- KPI Summary Stats --}}
        @php
            $stats = $this->stats;
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
                    <x-heroicon-o-inbox-stack class="w-5 h-5" />
                </div>
                <div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">{{ $stats['total'] }}</div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Queries</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                    <x-heroicon-o-clock class="w-5 h-5" />
                </div>
                <div>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ $stats['open'] }}</div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Awaiting Reply</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-chat-bubble-oval-left-ellipsis class="w-5 h-5" />
                </div>
                <div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $stats['whatsapp'] }}</div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">WhatsApp Inquiries</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400">
                    <x-heroicon-o-check-badge class="w-5 h-5" />
                </div>
                <div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">{{ $stats['resolved'] }}</div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Resolved Queries</div>
                </div>
            </div>
        </div>

        {{-- MAIN CONSOLE: Chat View vs Data Table View --}}
        @if ($viewMode === 'table')
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden p-2">
                {{ $this->table }}
            </div>
        @else
            @php
                $queries = $this->queries;
                $activeQuery = $this->selectedQuery;
            @endphp

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {{-- LEFT PANE: Conversations List (lg:col-span-4) --}}
                <div class="lg:col-span-4 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col h-[760px] overflow-hidden">
                    {{-- Search & Filters Header --}}
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800 space-y-3 bg-gray-50/50 dark:bg-gray-900/50">
                        {{-- Search Input --}}
                        <div class="relative">
                            <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-3 pointer-events-none" />
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search student name, phone, email..."
                                class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        {{-- Filter Pill Tabs --}}
                        <div class="flex items-center gap-1 overflow-x-auto pb-0.5 text-[11px] font-semibold scrollbar-none">
                            <button
                                type="button"
                                wire:click="$set('filter', 'all')"
                                class="px-2.5 py-1 rounded-lg shrink-0 transition {{ $filter === 'all' ? 'bg-blue-600 text-white font-bold' : 'bg-gray-200/70 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}"
                            >
                                All ({{ $stats['total'] }})
                            </button>
                            <button
                                type="button"
                                wire:click="$set('filter', 'open')"
                                class="px-2.5 py-1 rounded-lg shrink-0 transition {{ $filter === 'open' ? 'bg-amber-600 text-white font-bold' : 'bg-gray-200/70 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}"
                            >
                                Open ({{ $stats['open'] }})
                            </button>
                            <button
                                type="button"
                                wire:click="$set('filter', 'whatsapp')"
                                class="px-2.5 py-1 rounded-lg shrink-0 transition {{ $filter === 'whatsapp' ? 'bg-emerald-600 text-white font-bold' : 'bg-gray-200/70 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}"
                            >
                                WhatsApp ({{ $stats['whatsapp'] }})
                            </button>
                            <button
                                type="button"
                                wire:click="$set('filter', 'replied')"
                                class="px-2.5 py-1 rounded-lg shrink-0 transition {{ $filter === 'replied' ? 'bg-purple-600 text-white font-bold' : 'bg-gray-200/70 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}"
                            >
                                Replied
                            </button>
                        </div>
                    </div>

                    {{-- Scrollable List of Students --}}
                    <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($queries as $item)
                            @php
                                $isSelected = $selectedQueryId === $item->id;
                                $isReplied = filled($item->admin_reply);
                            @endphp
                            <div
                                wire:key="query-item-{{ $item->id }}"
                                wire:click="selectQuery({{ $item->id }})"
                                class="p-3.5 cursor-pointer transition flex items-start gap-3 {{ $isSelected ? 'bg-blue-50/70 dark:bg-blue-950/40 border-l-4 border-blue-600' : 'hover:bg-gray-50 dark:hover:bg-gray-800/60' }}"
                            >
                                {{-- Avatar with Initials --}}
                                <div class="size-10 rounded-full flex items-center justify-center font-bold text-xs shrink-0 {{ $isSelected ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300' }}">
                                    {{ strtoupper(substr($item->name ?: 'S', 0, 1)) }}
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-gray-900 dark:text-white truncate">
                                            {{ $item->name }}
                                        </h4>
                                        <span class="text-[10px] text-gray-400 shrink-0">
                                            {{ $item->created_at?->diffForHumans(null, true, true) }}
                                        </span>
                                    </div>

                                    {{-- Phone Number Row --}}
                                    <div class="flex items-center gap-1.5 mt-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                        <x-heroicon-m-phone class="w-3 h-3 shrink-0" />
                                        <span>{{ $item->phone ? '+' . ltrim($item->phone, '+') : 'No phone' }}</span>
                                    </div>

                                    {{-- Topic & Snippet --}}
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-1 mt-1">
                                        {{ $item->message }}
                                    </p>

                                    <div class="flex items-center gap-1.5 mt-2">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                            {{ $item->category }}
                                        </span>

                                        @if ($item->channel === 'whatsapp')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400">
                                                WhatsApp
                                            </span>
                                        @endif

                                        @if ($isReplied)
                                            <span class="ml-auto inline-flex items-center gap-0.5 text-[10px] font-bold text-purple-600 dark:text-purple-400">
                                                <x-heroicon-m-check class="w-3 h-3" />
                                                Replied
                                            </span>
                                        @else
                                            <span class="ml-auto inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400">
                                                Pending
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-gray-400 space-y-2">
                                <x-heroicon-o-inbox class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700" />
                                <p class="text-xs font-semibold text-gray-500">No support queries found</p>
                                <button
                                    type="button"
                                    wire:click="createTestQuery"
                                    class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline inline-block mt-1"
                                >
                                    + Add Sample Query
                                </button>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- RIGHT PANE: The Active Screen (Where person sees number & whom replying to) (lg:col-span-8) --}}
                <div class="lg:col-span-8 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col h-[760px] overflow-hidden">
                    @if ($activeQuery)
                        @php
                            $cleanPhone = preg_replace('/\D/', '', (string) $activeQuery->phone);
                            if (! str_starts_with($cleanPhone, '91') && strlen($cleanPhone) === 10) {
                                $cleanPhone = '91' . $cleanPhone;
                            }
                            $waGreeting = urlencode("Hello {$activeQuery->name}, this is from My Merchant Navy regarding your inquiry ({$activeQuery->category}). How can we assist you today?");
                            $waUrl = "https://wa.me/{$cleanPhone}?text={$waGreeting}";
                        @endphp

                        {{-- 1. Prominent Student Identity & Phone Banner --}}
                        <div class="p-5 border-b border-gray-200 dark:border-gray-800 bg-gradient-to-r from-blue-50/60 via-slate-50/50 to-white dark:from-gray-900 dark:to-gray-850">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                {{-- Left: Avatar & Name & Email --}}
                                <div class="flex items-center gap-3.5">
                                    <div class="size-12 rounded-full bg-blue-600 text-white font-extrabold text-base flex items-center justify-center shadow-md">
                                        {{ strtoupper(substr($activeQuery->name ?: 'S', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-base font-extrabold text-gray-900 dark:text-white">
                                                {{ $activeQuery->name }}
                                            </h3>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-800 dark:bg-blue-950/70 dark:text-blue-300">
                                                {{ $activeQuery->category }}
                                            </span>
                                        </div>
                                        <a href="mailto:{{ $activeQuery->email }}" class="text-xs text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 font-mono flex items-center gap-1 mt-0.5">
                                            <x-heroicon-m-envelope class="w-3.5 h-3.5 text-gray-400" />
                                            {{ $activeQuery->email }}
                                        </a>
                                    </div>
                                </div>

                                {{-- Right: Very Clear Phone Display & WhatsApp Button --}}
                                <div class="flex items-center flex-wrap gap-2">
                                    {{-- Highlighted Phone Number Badge --}}
                                    <div class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 px-3.5 py-2 rounded-xl">
                                        <x-heroicon-s-phone class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                        <div>
                                            <span class="block text-[10px] uppercase font-bold text-emerald-800 dark:text-emerald-400 leading-none">Phone / WhatsApp</span>
                                            <span class="text-sm font-extrabold text-emerald-900 dark:text-emerald-200 tracking-wide font-mono">
                                                {{ $activeQuery->phone ? '+' . ltrim($activeQuery->phone, '+') : 'Not Provided' }}
                                            </span>
                                        </div>
                                    </div>

                                    @if ($activeQuery->phone)
                                        <a
                                            href="{{ $waUrl }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition active:scale-95"
                                        >
                                            <x-heroicon-o-chat-bubble-oval-left-ellipsis class="w-4 h-4" />
                                            Open WhatsApp
                                        </a>
                                    @endif

                                    {{-- Quick Status Dropdown --}}
                                    <div class="relative">
                                        <select
                                            wire:change="updateStatus($event.target.value)"
                                            class="text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-2.5 py-2 font-semibold focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="open" {{ $activeQuery->status === 'open' ? 'selected' : '' }}>🟡 Open</option>
                                            <option value="in_progress" {{ $activeQuery->status === 'in_progress' ? 'selected' : '' }}>🟠 In Progress</option>
                                            <option value="resolved" {{ $activeQuery->status === 'resolved' ? 'selected' : '' }}>🟢 Resolved</option>
                                            <option value="closed" {{ $activeQuery->status === 'closed' ? 'selected' : '' }}>⚪ Closed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 2. Conversation Transcript Area --}}
                        <div class="flex-1 overflow-y-auto p-5 space-y-4 bg-gray-50/30 dark:bg-gray-950/20">
                            {{-- Student Inbound Query Bubble --}}
                            <div class="flex items-start gap-3 max-w-2xl">
                                <div class="size-8 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 font-bold text-xs flex items-center justify-center shrink-0 mt-1">
                                    {{ strtoupper(substr($activeQuery->name ?: 'S', 0, 1)) }}
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="font-bold text-gray-900 dark:text-white">{{ $activeQuery->name }}</span>
                                        <span class="text-[11px] text-gray-400">{{ $activeQuery->created_at?->format('d M Y, H:i A') }}</span>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 uppercase">
                                            {{ $activeQuery->channel === 'whatsapp' ? 'WhatsApp' : 'Web Chatbot' }}
                                        </span>
                                    </div>
                                    <div class="rounded-2xl rounded-tl-sm bg-white dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700 text-xs sm:text-sm text-gray-800 dark:text-gray-100 shadow-xs leading-relaxed whitespace-pre-wrap">
                                        {{ $activeQuery->message }}
                                    </div>
                                    @if ($activeQuery->ip_address)
                                        <div class="text-[10px] text-gray-400 font-mono">
                                            IP: {{ $activeQuery->ip_address }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Site Manager / Faculty Reply Bubble (If exists) --}}
                            @if (filled($activeQuery->admin_reply))
                                <div class="flex items-start gap-3 max-w-2xl ml-auto flex-row-reverse">
                                    <div class="size-8 rounded-full bg-purple-600 text-white font-bold text-xs flex items-center justify-center shrink-0 mt-1">
                                        FA
                                    </div>
                                    <div class="space-y-1 text-right">
                                        <div class="flex items-center justify-end gap-2 text-xs">
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 uppercase">
                                                Faculty Response
                                            </span>
                                            <span class="font-bold text-gray-900 dark:text-white">Academic Team</span>
                                            <span class="text-[11px] text-gray-400">{{ $activeQuery->replied_at?->format('d M, H:i A') }}</span>
                                        </div>
                                        <div class="rounded-2xl rounded-tr-sm bg-purple-50 dark:bg-purple-950/40 p-4 border border-purple-200 dark:border-purple-800 text-xs sm:text-sm text-purple-950 dark:text-purple-100 shadow-xs leading-relaxed text-left whitespace-pre-wrap">
                                            {{ $activeQuery->admin_reply }}
                                        </div>
                                        <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center justify-end gap-1">
                                            <x-heroicon-m-check-badge class="w-3.5 h-3.5" />
                                            Delivered to student chatbot & sent via email
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- 3. Replying Console (Bottom Form) --}}
                        <div class="p-4 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 space-y-3">
                            {{-- Canned Response Quick Chips --}}
                            <div class="flex items-center gap-1.5 overflow-x-auto text-[11px] font-semibold text-gray-600 dark:text-gray-400 pb-1 scrollbar-none">
                                <span class="text-gray-400 shrink-0 font-medium">Quick Template:</span>
                                <button
                                    type="button"
                                    wire:click="setQuickReply('greeting')"
                                    class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-950 hover:text-blue-600 shrink-0 transition"
                                >
                                    👋 Greeting
                                </button>
                                <button
                                    type="button"
                                    wire:click="setQuickReply('resolved')"
                                    class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-emerald-50 dark:hover:bg-emerald-950 hover:text-emerald-600 shrink-0 transition"
                                >
                                    ✅ Doubt Resolved
                                </button>
                                <button
                                    type="button"
                                    wire:click="setQuickReply('call')"
                                    class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-amber-50 dark:hover:bg-amber-950 hover:text-amber-600 shrink-0 transition"
                                >
                                    📞 Call Scheduled
                                </button>
                                <button
                                    type="button"
                                    wire:click="setQuickReply('payment')"
                                    class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-purple-50 dark:hover:bg-purple-950 hover:text-purple-600 shrink-0 transition"
                                >
                                    🎉 Course Enrolled
                                </button>
                            </div>

                            {{-- Textarea --}}
                            <div>
                                <textarea
                                    wire:model="replyText"
                                    rows="3"
                                    placeholder="Type your official reply to {{ $activeQuery->name }}... (This reply is shown in the student's chatbot and sent via email)"
                                    class="w-full p-3 text-xs sm:text-sm rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                ></textarea>
                            </div>

                            {{-- Actions Row --}}
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
                                <div class="flex items-center gap-4 text-xs">
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input
                                            type="checkbox"
                                            wire:model="sendEmail"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="text-gray-700 dark:text-gray-300 font-medium">Send Email Notification</span>
                                    </label>

                                    <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                                        <span>Status:</span>
                                        <select
                                            wire:model="replyStatus"
                                            class="text-xs rounded-lg border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white py-1 px-2 font-semibold"
                                        >
                                            <option value="resolved">Mark as Resolved</option>
                                            <option value="in_progress">Mark as In Progress</option>
                                            <option value="open">Keep Open</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="submitReply"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md transition active:scale-95 disabled:opacity-60"
                                    >
                                        <x-heroicon-m-paper-airplane class="w-4 h-4" />
                                        <span wire:loading.remove wire:target="submitReply">Send Reply to Student</span>
                                        <span wire:loading wire:target="submitReply">Dispatching...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Welcome / Empty State Screen --}}
                        <div class="flex-1 flex flex-col items-center justify-center p-8 text-center space-y-4">
                            <div class="size-16 rounded-3xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shadow-xs">
                                <x-heroicon-o-chat-bubble-left-ellipsis class="w-8 h-8" />
                            </div>
                            <div class="max-w-md space-y-1">
                                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">
                                    No Conversation Selected
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    Choose an incoming student inquiry from the left panel to inspect their details, view their verified mobile number, chat on WhatsApp, or send a resolution.
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="createTestQuery"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition active:scale-95"
                            >
                                <x-heroicon-o-plus class="w-4 h-4" />
                                Generate Sample Test Inquiry
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>