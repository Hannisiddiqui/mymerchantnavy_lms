<x-filament-panels::page>
    @php
        $queries = $this->queries;
        $active = $this->selectedQuery;
        $stats = $this->stats;
        $fmtPhone = function ($raw) {
            $d = preg_replace('/\D/', '', (string) $raw);
            if (strlen($d) === 12 && str_starts_with($d, '91')) { $d = substr($d, 2); }
            return strlen($d) === 10 ? '+91 '.substr($d, 0, 5).' '.substr($d, 5) : ($raw ? '+'.ltrim($raw, '+') : 'No phone');
        };
    @endphp

    <style>
        .sup-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 170px), 1fr)); gap: .875rem; }
        .sup-stat { background: #fff; border: 1px solid var(--gray-200); border-radius: .875rem; padding: .875rem 1rem; }
        .dark .sup-stat { background: var(--gray-900); border-color: rgba(255,255,255,.1); }
        .sup-stat small { display: block; font-size: .6875rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--gray-500); }
        .sup-stat b { display: block; margin-top: .25rem; font-size: 1.5rem; line-height: 1.1; color: #0b1e3d; }
        .dark .sup-stat b { color: #fff; }

        .sup-shell { display: grid; grid-template-columns: minmax(0, 1fr); background: #fff; border: 1px solid var(--gray-200); border-radius: 1rem; overflow: hidden; min-height: 640px; }
        .dark .sup-shell { background: var(--gray-900); border-color: rgba(255,255,255,.1); }
        @media (min-width: 1024px) { .sup-shell { grid-template-columns: 350px minmax(0, 1fr); height: calc(100vh - 17rem); min-height: 620px; } }

        .sup-list { display: flex; flex-direction: column; min-height: 0; border-bottom: 1px solid var(--gray-200); background: #f8fafc; }
        .dark .sup-list { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.1); }
        @media (min-width: 1024px) { .sup-list { border-bottom: 0; border-right: 1px solid var(--gray-200); } .dark .sup-list { border-right-color: rgba(255,255,255,.1); } }
        .sup-list-head { padding: 1rem 1rem .75rem; display: flex; flex-direction: column; gap: .75rem; }
        .sup-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: .25rem; padding: .25rem; background: #e8edf5; border-radius: .75rem; }
        .dark .sup-tabs { background: rgba(255,255,255,.08); }
        .sup-tab { border: 0; background: transparent; border-radius: .5rem; padding: .5rem; font-size: .8125rem; font-weight: 600; color: #475569; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: .375rem; }
        .sup-tab.on { background: #0b1e3d; color: #fff; box-shadow: 0 1px 3px rgba(11,30,61,.3); }
        .sup-tab svg { width: 1rem; height: 1rem; }
        .sup-search { width: 100%; border: 1px solid var(--gray-300); border-radius: .625rem; padding: .5rem .75rem; font-size: .875rem; background: #fff; color: inherit; }
        .dark .sup-search { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.15); }
        .sup-pills { display: flex; flex-wrap: wrap; gap: .375rem; }
        .sup-pill { border: 1px solid var(--gray-300); background: #fff; border-radius: 999px; padding: .1875rem .75rem; font-size: .75rem; font-weight: 600; color: #475569; cursor: pointer; }
        .dark .sup-pill { background: transparent; border-color: rgba(255,255,255,.2); color: var(--gray-300); }
        .sup-pill.on { background: #2563eb; border-color: #2563eb; color: #fff; }
        .sup-items { flex: 1 1 auto; overflow-y: auto; max-height: 360px; }
        @media (min-width: 1024px) { .sup-items { max-height: none; } }
        .sup-item { display: flex; gap: .75rem; width: 100%; text-align: left; padding: .75rem 1rem; border: 0; border-top: 1px solid var(--gray-200); background: transparent; cursor: pointer; color: inherit; }
        .dark .sup-item { border-top-color: rgba(255,255,255,.08); }
        .sup-item:hover { background: rgba(37,99,235,.05); }
        .sup-item.on { background: rgba(37,99,235,.1); box-shadow: inset 3px 0 0 #2563eb; }
        .sup-av { flex: none; width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-weight: 700; color: #fff; background: #0b1e3d; font-size: .9375rem; }
        .sup-item-main { min-width: 0; flex: 1; }
        .sup-row { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; }
        .sup-name { font-weight: 600; font-size: .875rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sup-time { flex: none; font-size: .6875rem; color: var(--gray-500); }
        .sup-sub { font-size: .75rem; color: #2563eb; margin-top: .0625rem; }
        .sup-snip { font-size: .8125rem; color: var(--gray-500); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: .125rem; }
        .sup-dot { display: inline-block; width: .5rem; height: .5rem; border-radius: 50%; margin-right: .375rem; background: #f59e0b; }
        .sup-dot.done { background: #16a34a; }
        .sup-empty { padding: 2.5rem 1rem; text-align: center; color: var(--gray-500); font-size: .875rem; }

        .sup-detail { display: flex; flex-direction: column; min-width: 0; min-height: 0; }
        .sup-dhead { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; padding: .875rem 1.125rem; border-bottom: 1px solid var(--gray-200); }
        .dark .sup-dhead { border-bottom-color: rgba(255,255,255,.1); }
        .sup-dhead .grow { flex: 1 1 14rem; min-width: 0; }
        .sup-dtitle { font-weight: 700; font-size: 1rem; color: #0b1e3d; }
        .dark .sup-dtitle { color: #fff; }
        .sup-dmeta { font-size: .8125rem; color: var(--gray-500); overflow-wrap: anywhere; }
        .sup-btn { display: inline-flex; align-items: center; gap: .375rem; border: 1px solid transparent; border-radius: .625rem; padding: .5rem .875rem; font-size: .8125rem; font-weight: 600; cursor: pointer; text-decoration: none; line-height: 1.2; }
        .sup-btn svg { width: 1rem; height: 1rem; }
        .sup-btn.navy { background: #0b1e3d; color: #fff; }
        .sup-btn.navy:hover { background: #12306a; }
        .sup-btn.blue { background: #2563eb; color: #fff; }
        .sup-btn.blue:hover { background: #1d4ed8; }
        .sup-btn.wa { background: #16a34a; color: #fff; }
        .sup-btn.wa:hover { background: #15803d; }
        .sup-btn.ghost { background: #fff; border-color: var(--gray-300); color: #334155; }
        .dark .sup-btn.ghost { background: transparent; border-color: rgba(255,255,255,.2); color: var(--gray-200); }
        .sup-btn.ghost:hover { background: #f1f5f9; }

        .sup-thread { flex: 1 1 auto; overflow-y: auto; padding: 1.25rem; background: #f1f5f9; display: flex; flex-direction: column; gap: .875rem; min-height: 260px; }
        .dark .sup-thread { background: rgba(255,255,255,.03); }
        .sup-chip { align-self: center; background: #fff; border: 1px solid var(--gray-200); color: var(--gray-500); border-radius: 999px; font-size: .6875rem; font-weight: 600; padding: .1875rem .75rem; }
        .dark .sup-chip { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.1); }
        .sup-bubble { max-width: min(34rem, 86%); border-radius: 1rem; padding: .625rem .875rem; font-size: .875rem; line-height: 1.5; overflow-wrap: anywhere; }
        .sup-bubble span.t { display: block; white-space: pre-wrap; }
        .sup-bubble small { display: block; font-size: .6875rem; margin-top: .25rem; opacity: .7; }
        .sup-bubble b { display: block; font-size: .75rem; margin-bottom: .125rem; }
        .sup-in { align-self: flex-start; background: #fff; border: 1px solid var(--gray-200); border-top-left-radius: .25rem; color: #0f172a; }
        .dark .sup-in { background: var(--gray-800); border-color: rgba(255,255,255,.1); color: #f8fafc; }
        .sup-out { align-self: flex-end; background: #2563eb; color: #fff; border-top-right-radius: .25rem; }
        .sup-out b { color: #bfdbfe; }
        .sup-notice { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; border-radius: .75rem; padding: .625rem .875rem; font-size: .8125rem; }
        .dark .sup-notice { background: rgba(37,99,235,.15); border-color: rgba(96,165,250,.3); color: #bfdbfe; }

        .sup-quick { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem; padding: .625rem 1.125rem 0; }
        .sup-quick span { font-size: .75rem; font-weight: 600; color: var(--gray-500); margin-right: .25rem; }
        .sup-composer { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .625rem; padding: .75rem 1.125rem 1rem; }
        .sup-composer textarea { flex: 1 1 16rem; min-height: 2.75rem; max-height: 9rem; resize: vertical; border: 1px solid var(--gray-300); border-radius: .75rem; padding: .625rem .875rem; font-size: .875rem; background: #fff; color: inherit; font-family: inherit; }
        .dark .sup-composer textarea, .dark .sup-form input, .dark .sup-form textarea, .dark .sup-form select { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.15); }

        .sup-form { flex: 1 1 auto; overflow-y: auto; padding: 1.125rem; display: flex; flex-direction: column; gap: .875rem; }
        .sup-form label { display: block; font-size: .75rem; font-weight: 600; color: var(--gray-600); margin-bottom: .25rem; }
        .sup-form input, .sup-form textarea, .sup-form select { width: 100%; border: 1px solid var(--gray-300); border-radius: .625rem; padding: .5625rem .75rem; font-size: .875rem; background: #fff; color: inherit; font-family: inherit; }
        .sup-form textarea { min-height: 15rem; resize: vertical; line-height: 1.55; }
        .sup-grid2 { display: grid; gap: .875rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 720px) { .sup-grid2 { grid-template-columns: 1fr 1fr; } }
        .sup-templates { display: flex; flex-wrap: wrap; gap: .375rem; align-items: center; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: .75rem; padding: .625rem .75rem; }
        .dark .sup-templates { background: rgba(37,99,235,.12); border-color: rgba(96,165,250,.25); }
        .sup-templates > span { font-size: .75rem; font-weight: 700; color: #1e40af; margin-right: .25rem; }
        .dark .sup-templates > span { color: #bfdbfe; }
        .sup-foot { display: flex; flex-wrap: wrap; align-items: center; gap: .625rem; padding: .875rem 1.125rem; border-top: 1px solid var(--gray-200); }
        .dark .sup-foot { border-top-color: rgba(255,255,255,.1); }
        .sup-foot .grow { flex: 1 1 auto; }
        .sup-foot select { border: 1px solid var(--gray-300); border-radius: .625rem; padding: .4375rem .625rem; font-size: .8125rem; background: #fff; }
        .sup-placeholder { flex: 1; display: grid; place-items: center; padding: 3rem 1rem; text-align: center; color: var(--gray-500); }
    </style>

    {{-- numbers --}}
    <div class="sup-stats">
        <div class="sup-stat"><small>Open</small><b>{{ $stats['open'] }}</b></div>
        <div class="sup-stat"><small>Resolved</small><b>{{ $stats['resolved'] }}</b></div>
        <div class="sup-stat"><small>From WhatsApp</small><b>{{ $stats['whatsapp'] }}</b></div>
        <div class="sup-stat"><small>All queries</small><b>{{ $stats['total'] }}</b></div>
    </div>

    <div class="sup-shell">
        {{-- LIST --}}
        <aside class="sup-list">
            <div class="sup-list-head">
                <div class="sup-tabs">
                    <button type="button" wire:click="$set('activeTab', 'whatsapp')" class="sup-tab {{ $activeTab === 'whatsapp' ? 'on' : '' }}">
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" /> Chat
                    </button>
                    <button type="button" wire:click="$set('activeTab', 'email')" class="sup-tab {{ $activeTab === 'email' ? 'on' : '' }}">
                        <x-filament::icon icon="heroicon-o-envelope" /> Email
                    </button>
                </div>
                <input type="search" class="sup-search" wire:model.live.debounce.300ms="search" placeholder="Search name, phone, email…">
                <div class="sup-pills">
                    @foreach (['all' => 'All', 'open' => 'Open', 'resolved' => 'Resolved'] as $key => $label)
                        <button type="button" wire:click="setStatusFilter('{{ $key }}')" class="sup-pill {{ $statusFilter === $key ? 'on' : '' }}">{{ $label }}</button>
                    @endforeach
                    @if ($activeTab === 'email')
                        <button type="button" wire:click="newEmailCompose" class="sup-pill" style="margin-left:auto;background:#0b1e3d;color:#fff;border-color:#0b1e3d">+ New email</button>
                    @endif
                </div>
            </div>

            <div class="sup-items">
                @forelse ($queries as $item)
                    <button type="button" wire:key="q-{{ $item->id }}" wire:click="selectQuery({{ $item->id }})" class="sup-item {{ $selectedQueryId === $item->id ? 'on' : '' }}">
                        <span class="sup-av">{{ strtoupper(mb_substr($item->name ?: '?', 0, 1)) }}</span>
                        <span class="sup-item-main">
                            <span class="sup-row">
                                <span class="sup-name"><span class="sup-dot {{ $item->status === 'open' ? '' : 'done' }}"></span>{{ $item->name }}</span>
                                <span class="sup-time">{{ $item->created_at?->format('d M, h:i A') }}</span>
                            </span>
                            <span class="sup-sub" style="display:block">{{ $activeTab === 'whatsapp' ? $fmtPhone($item->phone) : $item->email }}</span>
                            <span class="sup-snip" style="display:block">{{ $item->category }}: {{ $item->message }}</span>
                        </span>
                    </button>
                @empty
                    <div class="sup-empty">No queries match. Try another filter or search.</div>
                @endforelse
            </div>
        </aside>

        {{-- DETAIL --}}
        <section class="sup-detail">
            @if ($activeTab === 'whatsapp')
                @if ($active)
                    <div class="sup-dhead">
                        <span class="sup-av">{{ strtoupper(mb_substr($active->name ?: '?', 0, 1)) }}</span>
                        <div class="grow">
                            <div class="sup-dtitle">{{ $active->name }}</div>
                            <div class="sup-dmeta">{{ $fmtPhone($active->phone) }} · {{ $active->email }}</div>
                        </div>
                        @if ($active->status === 'open')
                            <button type="button" class="sup-btn ghost" wire:click="setQueryStatus({{ $active->id }}, 'resolved')"><x-filament::icon icon="heroicon-o-check-circle" /> Mark resolved</button>
                        @else
                            <button type="button" class="sup-btn ghost" wire:click="setQueryStatus({{ $active->id }}, 'open')"><x-filament::icon icon="heroicon-o-arrow-uturn-left" /> Re-open</button>
                        @endif
                    </div>

                    <div class="sup-thread" wire:key="thread-{{ $active->id }}-{{ strlen((string) $active->admin_reply) }}" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                        <div class="sup-notice">Replies are saved here and emailed to the student. To also message on WhatsApp, use <b>Open in WhatsApp</b>: it opens the chat with your text ready to send.</div>
                        <span class="sup-chip">{{ $active->created_at?->format('d M Y') }} · {{ $active->category }}</span>
                        <div class="sup-bubble sup-in">
                            <b>{{ $active->name }}</b><span class="t">{{ $active->message }}</span>
                            <small>{{ $active->created_at?->format('h:i A') }}</small>
                        </div>
                        @foreach (array_filter(array_map('trim', explode("\n\n", (string) $active->admin_reply))) as $reply)
                            <div class="sup-bubble sup-out">
                                <b>My Merchant Navy</b><span class="t">{{ $reply }}</span>
                                @if ($loop->last && $active->replied_at)<small>{{ $active->replied_at->format('d M, h:i A') }}</small>@endif
                            </div>
                        @endforeach
                    </div>

                    <div class="sup-quick">
                        <span>Quick replies</span>
                        <button type="button" class="sup-pill" wire:click="insertChatSnippet(@js("Hello {$active->name}, your academic doubt has been reviewed by our faculty. Please check your LMS portal for the solution."))">Doubt solved</button>
                        <button type="button" class="sup-pill" wire:click="insertChatSnippet(@js("Hello {$active->name}, our counsellor will call you on {$fmtPhone($active->phone)} today. Are you available?"))">Call scheduled</button>
                        <button type="button" class="sup-pill" wire:click="insertChatSnippet(@js("Hello {$active->name}, your course access is now active at https://lms.mymerchantnavy.com."))">Course unlocked</button>
                        <button type="button" class="sup-pill" wire:click="insertChatSnippet(@js("Hello {$active->name}, we have sent the details to your email ({$active->email}). Please check your inbox."))">Email sent</button>
                    </div>
                    <div class="sup-composer">
                        <textarea wire:model="chatMessage" wire:keydown.ctrl.enter="sendReply" rows="2" placeholder="Type your reply… (Ctrl+Enter to send)"></textarea>
                        <button type="button" class="sup-btn navy" wire:click="sendReply" wire:loading.attr="disabled"><x-filament::icon icon="heroicon-o-paper-airplane" /> Save &amp; email</button>
                        @if ($this->whatsappUrl)
                            <a class="sup-btn wa" href="{{ $this->whatsappUrl }}" target="_blank" rel="noopener noreferrer"><x-filament::icon icon="heroicon-o-chat-bubble-oval-left" /> Open in WhatsApp</a>
                        @endif
                    </div>
                @else
                    <div class="sup-placeholder">Choose a conversation on the left to read and reply.</div>
                @endif
            @else
                <div class="sup-dhead">
                    <div class="grow">
                        <div class="sup-dtitle">Compose email</div>
                        <div class="sup-dmeta">Sent from your official support address.</div>
                    </div>
                    <button type="button" class="sup-btn ghost" wire:click="newEmailCompose"><x-filament::icon icon="heroicon-o-plus" /> Clear &amp; new</button>
                </div>
                <div class="sup-form">
                    <div class="sup-templates">
                        <span>Templates</span>
                        @foreach (['academic_doubt' => 'Academic doubt', 'dns_sponsorship' => 'Sponsorship counselling', 'batch_enrollment' => 'Batch enrolment', 'fee_payment' => 'Fee &amp; payment', 'test_analysis' => 'Test analysis'] as $k => $label)
                            <button type="button" class="sup-pill" wire:click="applyPrebuiltTemplate('{{ $k }}')">{!! $label !!}</button>
                        @endforeach
                    </div>
                    <div class="sup-grid2">
                        <div><label>To (email)</label><input type="email" wire:model="recipientEmail" placeholder="student@example.com"></div>
                        <div><label>Student name</label><input type="text" wire:model="recipientName"></div>
                    </div>
                    <div><label>Subject</label><input type="text" wire:model="emailSubject"></div>
                    <div><label>Message</label><textarea wire:model="emailBody"></textarea></div>
                </div>
                <div class="sup-foot">
                    <label style="font-size:.8125rem;font-weight:600">Mark query as</label>
                    <select wire:model="emailStatus"><option value="resolved">Resolved</option><option value="open">Open</option><option value="in_progress">In progress</option></select>
                    <span class="grow"></span>
                    <button type="button" class="sup-btn blue" wire:click="sendPlatformEmail" wire:loading.attr="disabled"><x-filament::icon icon="heroicon-o-paper-airplane" /> Send email</button>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
