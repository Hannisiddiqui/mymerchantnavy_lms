<x-filament-panels::page>
    <style>
        .mmn-comm-wrapper {
            display: flex;
            height: 780px;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.08);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .dark .mmn-comm-wrapper {
            background: #111b21;
            border-color: #222e35;
        }

        /* 1. Leftmost Icon Rail (WhatsApp Web Style) */
        .mmn-icon-rail {
            width: 64px;
            background: #202c33;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            shrink: 0;
            border-right: 1px solid #2a3942;
        }
        .mmn-rail-top {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            width: 100%;
        }
        .mmn-rail-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            color: #aebac1;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .mmn-rail-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        .mmn-rail-btn.active {
            background: rgba(0, 168, 132, 0.2);
            color: #00a884;
        }
        .mmn-rail-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #25d366;
            color: #111b21;
            font-size: 10px;
            font-weight: 800;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }
        .mmn-rail-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #00a884;
            color: #ffffff;
            font-weight: 800;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* 2. Conversations / Messages Sidebar */
        .mmn-list-pane {
            width: 340px;
            background: #ffffff;
            border-right: 1px solid #e9edef;
            display: flex;
            flex-direction: column;
            shrink: 0;
        }
        .dark .mmn-list-pane {
            background: #111b21;
            border-right-color: #222e35;
        }
        .mmn-list-header {
            padding: 16px;
            background: #f0f2f5;
            border-bottom: 1px solid #e9edef;
        }
        .dark .mmn-list-header {
            background: #202c33;
            border-bottom-color: #222e35;
        }
        .mmn-list-title {
            font-size: 18px;
            font-weight: 800;
            color: #111b21;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .dark .mmn-list-title {
            color: #e9edef;
        }
        .mmn-search-box {
            position: relative;
        }
        .mmn-search-input {
            width: 100%;
            background: #ffffff;
            border: 1px solid #e9edef;
            border-radius: 8px;
            padding: 8px 12px 8px 36px;
            font-size: 13px;
            color: #111b21;
            outline: none;
        }
        .dark .mmn-search-input {
            background: #111b21;
            border-color: #2a3942;
            color: #e9edef;
        }
        .mmn-search-icon {
            position: absolute;
            left: 10px;
            top: 9px;
            width: 16px;
            height: 16px;
            color: #8696a0;
        }

        .mmn-convo-list {
            flex: 1;
            overflow-y: auto;
        }
        .mmn-convo-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #f0f2f5;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .dark .mmn-convo-item {
            border-bottom-color: #202c33;
        }
        .mmn-convo-item:hover {
            background: #f5f6f6;
        }
        .dark .mmn-convo-item:hover {
            background: #202c33;
        }
        .mmn-convo-item.active {
            background: #e9edef;
        }
        .dark .mmn-convo-item.active {
            background: #2a3942;
        }
        .mmn-convo-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #00a884;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            shrink: 0;
        }
        .mmn-convo-details {
            flex: 1;
            min-width: 0;
        }
        .mmn-convo-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        .mmn-convo-name {
            font-size: 14px;
            font-weight: 700;
            color: #111b21;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dark .mmn-convo-name {
            color: #e9edef;
        }
        .mmn-convo-time {
            font-size: 11px;
            color: #8696a0;
        }
        .mmn-convo-phone {
            font-size: 12px;
            font-weight: 600;
            color: #00a884;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .mmn-convo-msg {
            font-size: 12px;
            color: #667781;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 3px;
        }
        .dark .mmn-convo-msg {
            color: #8696a0;
        }

        /* 3. Main Chat Viewport (WhatsApp Web Pattern) */
        .mmn-chat-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #efeae2;
            background-image: radial-gradient(#d1d7db 0.8px, transparent 0.8px);
            background-size: 18px 18px;
            min-width: 0;
        }
        .dark .mmn-chat-pane {
            background: #0b141a;
            background-image: radial-gradient(#1f2c34 0.8px, transparent 0.8px);
        }
        .mmn-chat-header {
            padding: 10px 18px;
            background: #f0f2f5;
            border-bottom: 1px solid #e9edef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            shrink: 0;
        }
        .dark .mmn-chat-header {
            background: #202c33;
            border-bottom-color: #222e35;
        }
        .mmn-chat-student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .mmn-chat-phone-title {
            font-size: 16px;
            font-weight: 800;
            color: #111b21;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dark .mmn-chat-phone-title {
            color: #e9edef;
        }
        .mmn-chat-subtitle {
            font-size: 12px;
            color: #667781;
        }
        .dark .mmn-chat-subtitle {
            color: #8696a0;
        }
        .mmn-chat-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mmn-wa-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #25d366;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.15s;
        }
        .mmn-wa-btn:hover {
            background: #1eb956;
        }

        .mmn-messages-box {
            flex: 1;
            padding: 20px 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .mmn-bubble-in {
            align-self: flex-start;
            background: #ffffff;
            color: #111b21;
            padding: 10px 14px;
            border-radius: 12px 12px 12px 2px;
            max-width: 75%;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .dark .mmn-bubble-in {
            background: #202c33;
            color: #e9edef;
        }
        .mmn-bubble-out {
            align-self: flex-end;
            background: #d9fdd3;
            color: #111b21;
            padding: 10px 14px;
            border-radius: 12px 12px 2px 12px;
            max-width: 75%;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .dark .mmn-bubble-out {
            background: #005c4b;
            color: #e9edef;
        }
        .mmn-bubble-sender {
            font-size: 11px;
            font-weight: 700;
            color: #00a884;
            margin-bottom: 4px;
        }
        .mmn-bubble-text {
            font-size: 13px;
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .mmn-bubble-meta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            font-size: 10px;
            color: #667781;
            margin-top: 4px;
        }
        .dark .mmn-bubble-meta {
            color: #8696a0;
        }

        .mmn-chat-input-bar {
            padding: 12px 16px;
            background: #f0f2f5;
            border-top: 1px solid #e9edef;
            display: flex;
            align-items: center;
            gap: 12px;
            shrink: 0;
        }
        .dark .mmn-chat-input-bar {
            background: #202c33;
            border-top-color: #222e35;
        }
        .mmn-chat-input {
            flex: 1;
            background: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 11px 16px;
            font-size: 14px;
            color: #111b21;
            outline: none;
        }
        .dark .mmn-chat-input {
            background: #2a3942;
            color: #e9edef;
        }
        .mmn-send-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #00a884;
            color: #ffffff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            shrink: 0;
        }
        .mmn-send-btn:hover {
            background: #028e70;
            transform: scale(1.05);
        }

        /* 4. Email / AI Composer Screen */
        .mmn-email-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            min-width: 0;
        }
        .dark .mmn-email-pane {
            background: #111b21;
        }
        .mmn-email-header {
            padding: 14px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dark .mmn-email-header {
            background: #1e293b;
            border-bottom-color: #334155;
        }
        .mmn-gmail-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ea4335;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
        }
        .mmn-gmail-btn:hover {
            background: #d93025;
        }
        .mmn-ai-bar {
            background: #f0fdf4;
            border-bottom: 1px solid #bbf7d0;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }
        .dark .mmn-ai-bar {
            background: rgba(6, 78, 59, 0.2);
            border-bottom-color: #065f46;
        }
        .mmn-ai-btn {
            background: #00a884;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .mmn-ai-btn:hover {
            background: #028e70;
        }
        .mmn-email-form {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 14px;
            overflow-y: auto;
        }
        .mmn-form-row {
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 8px;
        }
        .dark .mmn-form-row {
            border-bottom-color: #1e293b;
        }
        .mmn-form-label {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            width: 70px;
        }
        .mmn-form-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 14px;
            color: #0f172a;
            background: transparent;
        }
        .dark .mmn-form-input {
            color: #f8fafc;
        }
        .mmn-textarea {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            font-size: 13px;
            line-height: 1.6;
            outline: none;
            resize: none;
            color: #0f172a;
            background: #ffffff;
        }
        .dark .mmn-textarea {
            background: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }
        .mmn-email-footer {
            padding: 14px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dark .mmn-email-footer {
            background: #1e293b;
            border-top-color: #334155;
        }
    </style>

    @php
        $queries = $this->queries;
        $activeQuery = $this->selectedQuery;
        $stats = $this->stats;
    @endphp

    <div class="mmn-comm-wrapper">
        {{-- 1. LEFT ICON RAIL (WhatsApp Web Style) --}}
        <div class="mmn-icon-rail">
            <div class="mmn-rail-top">
                {{-- WhatsApp Icon Button --}}
                <button
                    type="button"
                    wire:click="$set('activeTab', 'whatsapp')"
                    class="mmn-rail-btn {{ $activeTab === 'whatsapp' ? 'active' : '' }}"
                    title="WhatsApp Chats"
                >
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                        <path d="M12.004 2c-5.523 0-10 4.477-10 10 0 1.767.458 3.428 1.258 4.873l-1.332 4.864 4.985-1.307c1.401.765 3.007 1.2 4.708 1.2 5.523 0 10-4.477 10-10s-4.477-10-10-10zm5.836 14.199c-.244.688-1.218 1.266-1.996 1.434-.531.114-1.226.206-3.557-.759-2.981-1.233-4.9-4.269-5.048-4.468-.146-.199-1.21-1.611-1.21-3.072 0-1.462.766-2.18 1.037-2.476.273-.298.595-.373.794-.373.199 0 .398.002.571.011.185.009.431-.07.674.512.244.586.832 2.03.905 2.179.073.149.122.323.024.522-.098.199-.147.323-.294.497-.147.174-.31.39-.443.523-.147.147-.302.308-.13.604.172.298.766 1.263 1.644 2.045 1.13 1.008 2.083 1.32 2.381 1.468.298.149.472.124.646-.075.174-.199.744-.868.943-1.166.199-.298.398-.249.671-.149.273.099 1.733.817 2.031.966.298.149.497.224.571.348.075.124.075.724-.169 1.412z"/>
                    </svg>
                    @if ($stats['open'] > 0)
                        <span class="mmn-rail-badge">{{ $stats['open'] }}</span>
                    @endif
                </button>

                {{-- Email / Gmail Icon Button --}}
                <button
                    type="button"
                    wire:click="$set('activeTab', 'email')"
                    class="mmn-rail-btn {{ $activeTab === 'email' ? 'active' : '' }}"
                    title="Student Emails & AI Composer"
                >
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </button>
            </div>

            {{-- Site Manager Profile Circle --}}
            <div class="mmn-rail-avatar" title="{{ auth('admin')->user()?->name ?? 'Site Manager' }}">
                {{ strtoupper(substr(auth('admin')->user()?->name ?: 'M', 0, 1)) }}
            </div>
        </div>

        {{-- 2. CONVERSATIONS LIST SIDEBAR --}}
        <div class="mmn-list-pane">
            <div class="mmn-list-header">
                <div class="mmn-list-title">
                    <span>{{ $activeTab === 'whatsapp' ? 'WhatsApp Chats' : 'Student Inquiries' }}</span>
                    <button
                        type="button"
                        wire:click="createTestQuery"
                        style="font-size: 11px; background: #00a884; color: #fff; padding: 4px 8px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700;"
                    >
                        + New Query
                    </button>
                </div>
                <div class="mmn-search-box">
                    <svg class="mmn-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search name, mobile number..."
                        class="mmn-search-input"
                    />
                </div>
            </div>

            <div class="mmn-convo-list">
                @forelse ($queries as $item)
                    @php
                        $isSelected = $selectedQueryId === $item->id;
                    @endphp
                    <div
                        wire:key="chat-list-{{ $item->id }}"
                        wire:click="selectQuery({{ $item->id }})"
                        class="mmn-convo-item {{ $isSelected ? 'active' : '' }}"
                    >
                        <div class="mmn-convo-avatar">
                            {{ strtoupper(substr($item->name ?: 'S', 0, 1)) }}
                        </div>
                        <div class="mmn-convo-details">
                            <div class="mmn-convo-top">
                                <span class="mmn-convo-name">{{ $item->name }}</span>
                                <span class="mmn-convo-time">{{ $item->created_at?->format('h:i A') }}</span>
                            </div>
                            <div class="mmn-convo-phone">
                                <span>📱 {{ $item->phone ? '+' . ltrim($item->phone, '+') : 'No phone' }}</span>
                            </div>
                            <div class="mmn-convo-msg">
                                {{ $item->message }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="padding: 32px 16px; text-align: center; color: #8696a0; font-size: 13px;">
                        No queries found.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- 3. MAIN WORKSPACE: WHATSAPP VS EMAIL --}}
        @if ($activeTab === 'whatsapp')
            {{-- WHATSAPP LIVE CHAT SCREEN --}}
            <div class="mmn-chat-pane">
                @if ($activeQuery)
                    @php
                        $cleanPhone = preg_replace('/\D/', '', (string) $activeQuery->phone);
                        if (! str_starts_with($cleanPhone, '91') && strlen($cleanPhone) === 10) {
                            $cleanPhone = '91' . $cleanPhone;
                        }
                        $waWebUrl = "https://web.whatsapp.com/send?phone={$cleanPhone}&text=" . urlencode("Hello {$activeQuery->name}, this is from My Merchant Navy regarding your inquiry ({$activeQuery->category}). How can we assist you?");
                    @endphp

                    {{-- WhatsApp Header --}}
                    <div class="mmn-chat-header">
                        <div class="mmn-chat-student-info">
                            <div class="mmn-convo-avatar" style="width: 40px; height: 40px;">
                                {{ strtoupper(substr($activeQuery->name ?: 'S', 0, 1)) }}
                            </div>
                            <div>
                                <div class="mmn-chat-phone-title">
                                    <span>{{ $activeQuery->phone ? '+' . ltrim($activeQuery->phone, '+') : $activeQuery->name }}</span>
                                    <span style="font-size: 11px; background: rgba(0, 168, 132, 0.15); color: #00a884; padding: 2px 6px; border-radius: 4px;">{{ $activeQuery->category }}</span>
                                </div>
                                <div class="mmn-chat-subtitle">
                                    {{ $activeQuery->name }} · {{ $activeQuery->email }}
                                </div>
                            </div>
                        </div>

                        <div class="mmn-chat-actions">
                            <a
                                href="{{ $waWebUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mmn-wa-btn"
                                title="Open WhatsApp Web in new tab"
                            >
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                    <path d="M12.004 2c-5.523 0-10 4.477-10 10 0 1.767.458 3.428 1.258 4.873l-1.332 4.864 4.985-1.307c1.401.765 3.007 1.2 4.708 1.2 5.523 0 10-4.477 10-10s-4.477-10-10-10z"/>
                                </svg>
                                Open WhatsApp Web
                            </a>
                        </div>
                    </div>

                    {{-- WhatsApp Messages Viewport --}}
                    <div class="mmn-messages-box">
                        {{-- Inbound Student Message --}}
                        <div class="mmn-bubble-in">
                            <div class="mmn-bubble-sender">{{ $activeQuery->name }} ({{ $activeQuery->phone }})</div>
                            <div class="mmn-bubble-text">{{ $activeQuery->message }}</div>
                            <div class="mmn-bubble-meta">
                                <span>{{ $activeQuery->created_at?->format('h:i A') }}</span>
                            </div>
                        </div>

                        {{-- Outbound Site Manager Reply --}}
                        @if (filled($activeQuery->admin_reply))
                            <div class="mmn-bubble-out">
                                <div class="mmn-bubble-sender" style="color: #075e54;">My Merchant Navy Faculty</div>
                                <div class="mmn-bubble-text">{{ $activeQuery->admin_reply }}</div>
                                <div class="mmn-bubble-meta">
                                    <span>{{ $activeQuery->replied_at?->format('h:i A') }}</span>
                                    <span style="color: #53bdeb; font-weight: 800;">✓✓</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- WhatsApp In-Dashboard Input Bar --}}
                    <div class="mmn-chat-input-bar">
                        <input
                            type="text"
                            wire:model="chatMessage"
                            wire:keydown.enter="sendChatMessage"
                            placeholder="Type a message to reply to {{ $activeQuery->name }} on WhatsApp/App..."
                            class="mmn-chat-input"
                        />
                        <button
                            type="button"
                            wire:click="sendChatMessage"
                            class="mmn-send-btn"
                            title="Send reply"
                        >
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                @else
                    <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #8696a0; font-size: 14px;">
                        Select a chat from the left to view messages and reply.
                    </div>
                @endif
            </div>
        @else
            {{-- EMAIL & AI COMPOSER SCREEN --}}
            <div class="mmn-email-pane">
                @if ($activeQuery)
                    @php
                        $gmailUrl = "https://mail.google.com/mail/?view=cm&fs=1&to=" . urlencode($activeQuery->email) . "&su=" . urlencode("Response to your inquiry: {$activeQuery->category}") . "&body=" . urlencode("Dear {$activeQuery->name},\n\nRegarding your inquiry: \"{$activeQuery->message}\"\n\n");
                    @endphp

                    {{-- Email Header --}}
                    <div class="mmn-email-header">
                        <div>
                            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">Compose Student Email</h3>
                            <span style="font-size: 12px; color: #64748b;">Replying to: {{ $activeQuery->name }} ({{ $activeQuery->email }})</span>
                        </div>
                        <a
                            href="{{ $gmailUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mmn-gmail-btn"
                            title="Open Google Mail in new tab"
                        >
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            Open in Google Mail
                        </a>
                    </div>

                    {{-- AI Writer Tool Bar --}}
                    <div class="mmn-ai-bar">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 12px; font-weight: 700; color: #166534;">✨ AI Mail Assistant:</span>
                            <button type="button" wire:click="generateAiEmail('doubt')" class="mmn-ai-btn">
                                💡 Solve Doubt
                            </button>
                            <button type="button" wire:click="generateAiEmail('counseling')" class="mmn-ai-btn">
                                📞 Counseling Guidance
                            </button>
                            <button type="button" wire:click="generateAiEmail('batch')" class="mmn-ai-btn">
                                📚 Batch Enrollment
                            </button>
                        </div>
                    </div>

                    {{-- Compose Form --}}
                    <div class="mmn-email-form">
                        <div class="mmn-form-row">
                            <span class="mmn-form-label">To:</span>
                            <input type="text" value="{{ $activeQuery->name }} <{{ $activeQuery->email }}>" readonly class="mmn-form-input" style="font-weight: 600;" />
                        </div>

                        <div class="mmn-form-row">
                            <span class="mmn-form-label">Subject:</span>
                            <input type="text" wire:model="emailSubject" class="mmn-form-input" placeholder="Subject line..." />
                        </div>

                        <textarea
                            wire:model="emailBody"
                            class="mmn-textarea"
                            placeholder="Write your email to {{ $activeQuery->name }} here or click an AI template above..."
                        ></textarea>
                    </div>

                    {{-- Email Action Footer --}}
                    <div class="mmn-email-footer">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 12px; color: #64748b;">Status:</span>
                            <select wire:model="emailStatus" style="font-size: 12px; padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                <option value="resolved">Mark Resolved</option>
                                <option value="in_progress">In Progress</option>
                                <option value="open">Keep Open</option>
                            </select>
                        </div>

                        <button
                            type="button"
                            wire:click="sendPlatformEmail"
                            style="background: #2563eb; color: #ffffff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;"
                        >
                            <span>Send Email to Student</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                @else
                    <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #8696a0; font-size: 14px;">
                        Select a query from the left to write and send an email.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
