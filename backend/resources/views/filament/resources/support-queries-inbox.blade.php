<x-filament-panels::page>
    <style>
        .mmn-comm-wrapper {
            display: flex;
            height: calc(100vh - 170px);
            min-height: 720px;
            max-height: 880px;
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
            width: 60px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            shrink: 0;
            border-right: 1px solid #d1d5db;
        }
        .dark .mmn-icon-rail {
            background: #202c33;
            border-right-color: #2a3942;
        }
        .mmn-rail-top {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            width: 100%;
        }
        .mmn-rail-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            color: #54656f;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .dark .mmn-rail-btn {
            color: #aebac1;
        }
        .mmn-rail-btn:hover {
            background: rgba(0, 0, 0, 0.06);
            color: #111b21;
        }
        .dark .mmn-rail-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        .mmn-rail-btn.active {
            background: #d9fdd3;
            color: #008069;
        }
        .dark .mmn-rail-btn.active {
            background: rgba(0, 168, 132, 0.25);
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
            cursor: pointer;
        }

        /* 2. Conversations Sidebar */
        .mmn-list-pane {
            width: 330px;
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
            padding: 14px 16px 10px;
            background: #f0f2f5;
            border-bottom: 1px solid #e9edef;
        }
        .dark .mmn-list-header {
            background: #202c33;
            border-bottom-color: #222e35;
        }
        .mmn-list-title {
            font-size: 17px;
            font-weight: 800;
            color: #111b21;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .dark .mmn-list-title {
            color: #e9edef;
        }
        .mmn-search-box {
            position: relative;
            margin-bottom: 8px;
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

        /* Filter Chips */
        .mmn-filter-chips {
            display: flex;
            align-items: center;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 2px;
        }
        .mmn-chip {
            border: none;
            background: #ffffff;
            color: #54656f;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 14px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s ease;
        }
        .dark .mmn-chip {
            background: #111b21;
            color: #8696a0;
        }
        .mmn-chip:hover {
            background: #e9edef;
        }
        .dark .mmn-chip:hover {
            background: #2a3942;
        }
        .mmn-chip.active {
            background: #d9fdd3;
            color: #008069;
            font-weight: 700;
        }
        .dark .mmn-chip.active {
            background: rgba(0, 168, 132, 0.25);
            color: #00a884;
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

        /* 3. Main Chat Viewport */
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
            padding: 12px 18px;
            background: #f0f2f5;
            border-bottom: 1px solid #e9edef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
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
            min-width: 0;
            flex: 1;
        }
        .mmn-chat-phone-title {
            font-size: 16px;
            font-weight: 800;
            color: #111b21;
            white-space: nowrap;
        }
        .dark .mmn-chat-phone-title {
            color: #e9edef;
        }
        .mmn-chat-subtitle {
            font-size: 12px;
            color: #667781;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dark .mmn-chat-subtitle {
            color: #8696a0;
        }
        .mmn-chat-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            shrink: 0;
        }
        .mmn-wa-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #25d366;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.15s;
        }
        .mmn-wa-btn:hover {
            background: #1eb956;
        }
        .mmn-wa-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 700;
            color: #008069;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(0, 168, 132, 0.12);
            white-space: nowrap;
            transition: all 0.15s;
        }
        .dark .mmn-wa-link-btn {
            color: #25d366;
            background: rgba(37, 211, 102, 0.15);
        }
        .mmn-wa-link-btn:hover {
            background: rgba(0, 168, 132, 0.22);
        }
        .dark .mmn-wa-link-btn:hover {
            background: rgba(37, 211, 102, 0.25);
        }

        .mmn-messages-box {
            flex: 1;
            padding: 20px 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .mmn-date-divider {
            align-self: center;
            background: #ffffff;
            color: #54656f;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
            text-transform: uppercase;
        }
        .dark .mmn-date-divider {
            background: #182229;
            color: #8696a0;
        }
        .mmn-security-notice {
            align-self: center;
            background: #ffeecd;
            color: #54656f;
            font-size: 11px;
            padding: 6px 14px;
            border-radius: 8px;
            max-width: 80%;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            margin-bottom: 6px;
        }
        .dark .mmn-security-notice {
            background: #182229;
            color: #f1b955;
            border: 1px solid #323d44;
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

        /* Quick Chat Snippets Bar */
        .mmn-chat-snippets {
            padding: 6px 16px;
            background: #f0f2f5;
            border-top: 1px solid #e9edef;
            display: flex;
            align-items: center;
            gap: 6px;
            overflow-x: auto;
            shrink: 0;
        }
        .dark .mmn-chat-snippets {
            background: #1c272e;
            border-top-color: #2a3942;
        }
        .mmn-snippet-btn {
            background: #ffffff;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s;
        }
        .dark .mmn-snippet-btn {
            background: #202c33;
            border-color: #2a3942;
            color: #d1d5db;
        }
        .mmn-snippet-btn:hover {
            background: #e9edef;
            color: #111b21;
        }

        .mmn-chat-input-bar {
            padding: 10px 16px;
            background: #f0f2f5;
            border-top: 1px solid #e9edef;
            display: flex;
            align-items: center;
            gap: 10px;
            shrink: 0;
        }
        .dark .mmn-chat-input-bar {
            background: #202c33;
            border-top-color: #222e35;
        }
        .mmn-icon-action-btn {
            background: transparent;
            border: none;
            color: #54656f;
            cursor: pointer;
            padding: 6px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }
        .dark .mmn-icon-action-btn {
            color: #aebac1;
        }
        .mmn-icon-action-btn:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #111b21;
        }
        .dark .mmn-icon-action-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        .mmn-chat-input {
            flex: 1;
            background: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 13px;
            color: #111b21;
            outline: none;
        }
        .dark .mmn-chat-input {
            background: #2a3942;
            color: #e9edef;
        }
        .mmn-send-btn {
            width: 40px;
            height: 40px;
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

        /* 4. Email / Prebuilt Templates Screen */
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

        /* Prebuilt Templates Bar */
        .mmn-template-bar {
            background: #f0fdf4;
            border-bottom: 1px solid #bbf7d0;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .dark .mmn-template-bar {
            background: rgba(6, 78, 59, 0.2);
            border-bottom-color: #065f46;
        }
        .mmn-template-pill {
            background: #ffffff;
            border: 1px solid #86efac;
            color: #166534;
            border-radius: 16px;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
        }
        .dark .mmn-template-pill {
            background: #1e293b;
            border-color: #059669;
            color: #6ee7b7;
        }
        .mmn-template-pill:hover {
            background: #00a884;
            color: #ffffff;
            border-color: #00a884;
        }

        .mmn-email-form {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow-y: auto;
        }
        .mmn-form-row {
            display: flex;
            align-items: center;
            gap: 12px;
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
            width: 75px;
            shrink: 0;
        }
        .mmn-form-input {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #0f172a;
            background: #ffffff;
            outline: none;
        }
        .dark .mmn-form-input {
            background: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }
        .mmn-form-input:focus {
            border-color: #2563eb;
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
            min-height: 240px;
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
            gap: 12px;
            flex-wrap: wrap;
        }
        .dark .mmn-email-footer {
            background: #1e293b;
            border-top-color: #334155;
        }
        .mmn-select {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #1e293b;
            outline: none;
        }
        .dark .mmn-select {
            background: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }
    </style>

    @php
        $queries = $this->queries;
        $activeQuery = $this->selectedQuery;
        $stats = $this->stats;
    @endphp

    <div class="mmn-comm-wrapper">
        {{-- 1. LEFT ICON RAIL --}}
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
                    title="Student Emails & Manual Composer"
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
                    <span>{{ $activeTab === 'whatsapp' ? 'Student Chats' : 'Student Inquiries' }}</span>
                    @if ($activeTab === 'email')
                        <button
                            type="button"
                            wire:click="newEmailCompose"
                            style="font-size: 11px; background: #2563eb; color: #fff; padding: 4px 8px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700;"
                            title="Compose email to any student address"
                        >
                            + New Mail
                        </button>
                    @endif
                </div>

                <div class="mmn-search-box">
                    <svg class="mmn-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search student name, phone..."
                        class="mmn-search-input"
                    />
                </div>

                {{-- Status Filter Chips --}}
                <div class="mmn-filter-chips">
                    <button
                        type="button"
                        wire:click="setStatusFilter('all')"
                        class="mmn-chip {{ $statusFilter === 'all' ? 'active' : '' }}"
                    >
                        All ({{ $stats['total'] }})
                    </button>
                    <button
                        type="button"
                        wire:click="setStatusFilter('open')"
                        class="mmn-chip {{ $statusFilter === 'open' ? 'active' : '' }}"
                    >
                        Open ({{ $stats['open'] }})
                    </button>
                    <button
                        type="button"
                        wire:click="setStatusFilter('resolved')"
                        class="mmn-chip {{ $statusFilter === 'resolved' ? 'active' : '' }}"
                    >
                        Resolved ({{ $stats['resolved'] }})
                    </button>
                </div>
            </div>

            <div class="mmn-convo-list">
                @forelse ($queries as $item)
                    @php
                        $isSelected = $selectedQueryId === $item->id;
                        $rawPhone = preg_replace('/\D/', '', (string) $item->phone);
                        if (strlen($rawPhone) === 10) {
                            $displayPhone = '+91 ' . substr($rawPhone, 0, 5) . ' ' . substr($rawPhone, 5);
                        } elseif (strlen($rawPhone) === 12 && str_starts_with($rawPhone, '91')) {
                            $displayPhone = '+91 ' . substr($rawPhone, 2, 5) . ' ' . substr($rawPhone, 7);
                        } else {
                            $displayPhone = $item->phone ? '+' . ltrim($item->phone, '+') : 'No phone';
                        }
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
                                <span>📱 {{ $displayPhone }}</span>
                            </div>
                            <div class="mmn-convo-msg">
                                @if (filled($item->admin_reply))
                                    <span style="color: #53bdeb; font-weight: 700; margin-right: 2px;">✓✓</span>
                                    {{ $item->admin_reply }}
                                @else
                                    {{ $item->message }}
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="padding: 40px 16px; text-align: center; color: #8696a0; font-size: 13px;">
                        <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 10px; opacity: 0.6;">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <p style="font-weight: 700; margin: 0 0 4px; color: #111b21;">No support inquiries found</p>
                        <p style="font-size: 12px; margin: 0; color: #667781;">Student doubts and WhatsApp queries raised via LMS will appear here.</p>
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
                        if (strlen($cleanPhone) === 10) {
                            $headerPhone = '+91 ' . substr($cleanPhone, 0, 5) . ' ' . substr($cleanPhone, 5);
                        } elseif (strlen($cleanPhone) === 12 && str_starts_with($cleanPhone, '91')) {
                            $headerPhone = '+91 ' . substr($cleanPhone, 2, 5) . ' ' . substr($cleanPhone, 7);
                        } else {
                            $headerPhone = $activeQuery->phone ? '+' . ltrim($activeQuery->phone, '+') : $activeQuery->name;
                        }
                        $waWebUrl = "https://web.whatsapp.com/send?phone={$cleanPhone}&text=" . urlencode("Hello {$activeQuery->name}, this is from My Merchant Navy regarding your inquiry ({$activeQuery->category}). How can we assist you?");
                        $waDirectUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode("Hello {$activeQuery->name}, this is from My Merchant Navy regarding your inquiry ({$activeQuery->category}).");
                    @endphp

                    {{-- WhatsApp Header with Zero Wrapping & Clean Alignments --}}
                    <div class="mmn-chat-header">
                        <div class="mmn-chat-student-info">
                            <div class="mmn-convo-avatar" style="width: 44px; height: 44px; font-size: 17px; shrink: 0;">
                                {{ strtoupper(substr($activeQuery->name ?: 'S', 0, 1)) }}
                            </div>
                            <div style="min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;">
                                    <span class="mmn-chat-phone-title">
                                        {{ $headerPhone }}
                                    </span>
                                    <span style="font-size: 11px; background: rgba(0, 168, 132, 0.15); color: #008069; padding: 3px 8px; border-radius: 6px; font-weight: 700; white-space: nowrap; max-width: 220px; overflow: hidden; text-overflow: ellipsis;">
                                        {{ $activeQuery->category }}
                                    </span>
                                </div>
                                <div class="mmn-chat-subtitle" style="margin-top: 2px;">
                                    {{ $activeQuery->name }} · {{ $activeQuery->email }} · Student Aspirant
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
                                <span>Chat on WhatsApp Web</span>
                            </a>
                            <a
                                href="{{ $waDirectUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mmn-wa-link-btn"
                                title="Direct WhatsApp App Link"
                            >
                                📱 App Link
                            </a>
                        </div>
                    </div>

                    {{-- WhatsApp Messages Viewport --}}
                    <div class="mmn-messages-box">
                        {{-- Security Notice --}}
                        <div class="mmn-security-notice">
                            🔒 Messages are synced securely with LMS Support. Student asked via LMS Chatbot.
                        </div>

                        <div class="mmn-date-divider">
                            {{ $activeQuery->created_at?->isToday() ? 'Today' : $activeQuery->created_at?->format('M d, Y') }}
                        </div>

                        {{-- Inbound Student Message --}}
                        <div class="mmn-bubble-in">
                            <div class="mmn-bubble-sender">{{ $activeQuery->name }} ({{ $headerPhone }})</div>
                            <div class="mmn-bubble-text">{{ $activeQuery->message }}</div>
                            <div class="mmn-bubble-meta">
                                <span>{{ $activeQuery->created_at?->format('h:i A') }}</span>
                            </div>
                        </div>

                        {{-- Outbound Site Manager Replies --}}
                        @if (filled($activeQuery->admin_reply))
                            @php
                                $replies = explode("\n\n", (string) $activeQuery->admin_reply);
                            @endphp
                            @foreach ($replies as $singleReply)
                                @if (filled(trim($singleReply)))
                                    <div class="mmn-bubble-out">
                                        <div class="mmn-bubble-sender" style="color: #075e54;">My Merchant Navy Faculty</div>
                                        <div class="mmn-bubble-text">{{ trim($singleReply) }}</div>
                                        <div class="mmn-bubble-meta">
                                            <span>{{ $activeQuery->replied_at?->format('h:i A') ?? now()->format('h:i A') }}</span>
                                            <span style="color: #53bdeb; font-weight: 800; font-size: 12px;">✓✓</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>

                    {{-- Quick Chat Snippets --}}
                    <div class="mmn-chat-snippets">
                        <span style="font-size: 11px; font-weight: 700; color: #64748b; margin-right: 4px;">Quick:</span>
                        <button
                            type="button"
                            wire:click="insertChatSnippet('Hello {{ $activeQuery->name }}, your academic doubt has been reviewed by our faculty. Please check your LMS portal for the step-by-step solution.')"
                            class="mmn-snippet-btn"
                        >
                            💡 Doubt Solved
                        </button>
                        <button
                            type="button"
                            wire:click="insertChatSnippet('Hello {{ $activeQuery->name }}, our senior maritime mentor is scheduling a counseling call on your phone ({{ $headerPhone }}). Are you available today?')"
                            class="mmn-snippet-btn"
                        >
                            📞 Call Scheduled
                        </button>
                        <button
                            type="button"
                            wire:click="insertChatSnippet('Hello {{ $activeQuery->name }}, your batch enrollment and mock test access have been activated on https://lms.mymerchantnavy.com.')"
                            class="mmn-snippet-btn"
                        >
                            📚 Batch Unlocked
                        </button>
                        <button
                            type="button"
                            wire:click="insertChatSnippet('Hello {{ $activeQuery->name }}, we have sent a detailed official resolution to your email ({{ $activeQuery->email }}). Please check your inbox.')"
                            class="mmn-snippet-btn"
                        >
                            ✉️ Email Sent
                        </button>
                    </div>

                    {{-- WhatsApp In-Dashboard Input Bar --}}
                    <div class="mmn-chat-input-bar">
                        {{-- Attachment '+' Button --}}
                        <button type="button" class="mmn-icon-action-btn" title="Add Attachment / Image">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </button>

                        {{-- Emoji Button --}}
                        <button type="button" class="mmn-icon-action-btn" title="Insert Emoji">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                <line x1="15" y1="9" x2="15.01" y2="9"></line>
                            </svg>
                        </button>

                        {{-- Main Message Text Box --}}
                        <input
                            type="text"
                            wire:model="chatMessage"
                            wire:keydown.enter="sendChatMessage"
                            placeholder="Type a message to reply on WhatsApp (Press Enter to send)..."
                            class="mmn-chat-input"
                        />

                        {{-- Green Circular Send Button --}}
                        <button
                            type="button"
                            wire:click="sendChatMessage"
                            class="mmn-send-btn"
                            title="Send reply (Enter)"
                        >
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                @else
                    {{-- Clean Empty State --}}
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; text-align: center; color: #8696a0;">
                        <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(0, 168, 132, 0.12); display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                            <svg viewBox="0 0 24 24" width="38" height="38" fill="#00a884">
                                <path d="M12.004 2c-5.523 0-10 4.477-10 10 0 1.767.458 3.428 1.258 4.873l-1.332 4.864 4.985-1.307c1.401.765 3.007 1.2 4.708 1.2 5.523 0 10-4.477 10-10s-4.477-10-10-10zm5.836 14.199c-.244.688-1.218 1.266-1.996 1.434-.531.114-1.226.206-3.557-.759-2.981-1.233-4.9-4.269-5.048-4.468-.146-.199-1.21-1.611-1.21-3.072 0-1.462.766-2.18 1.037-2.476.273-.298.595-.373.794-.373.199 0 .398.002.571.011.185.009.431-.07.674.512.244.586.832 2.03.905 2.179.073.149.122.323.024.522-.098.199-.147.323-.294.497-.147.174-.31.39-.443.523-.147.147-.302.308-.13.604.172.298.766 1.263 1.644 2.045 1.13 1.008 2.083 1.32 2.381 1.468.298.149.472.124.646-.075.174-.199.744-.868.943-1.166.199-.298.398-.249.671-.149.273.099 1.733.817 2.031.966.298.149.497.224.571.348.075.124.075.724-.169 1.412z"/>
                            </svg>
                        </div>
                        <h3 style="font-size: 18px; font-weight: 800; color: #111b21; margin: 0 0 6px;">My Merchant Navy Student Communications</h3>
                        <p style="font-size: 13px; max-width: 400px; line-height: 1.5; margin: 0; color: #64748b;">
                            Select an incoming student inquiry from the left to view their verified mobile number, chat in-dashboard, or reply via WhatsApp Web.
                        </p>
                    </div>
                @endif
            </div>
        @else
            {{-- EMAIL & PREBUILT TEMPLATES COMPOSER SCREEN --}}
            <div class="mmn-email-pane">
                @php
                    $gmailUrl = "https://mail.google.com/mail/?view=cm&fs=1&to=" . urlencode($recipientEmail) . "&su=" . urlencode($emailSubject) . "&body=" . urlencode($emailBody);
                @endphp

                {{-- Email Header --}}
                <div class="mmn-email-header">
                    <div>
                        <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;">Compose Student Email</h3>
                        <span style="font-size: 12px; color: #64748b;">
                            @if (filled($recipientEmail))
                                Sending to: <strong style="color: #2563eb;">{{ $recipientEmail }}</strong>
                            @else
                                Enter any student email address below or select an inquiry from the left.
                            @endif
                        </span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button
                            type="button"
                            wire:click="newEmailCompose"
                            style="font-size: 12px; font-weight: 700; color: #475569; background: #e2e8f0; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer;"
                        >
                            + Clear & New Email
                        </button>

                        @if (filled($recipientEmail))
                            <a
                                href="{{ $gmailUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mmn-gmail-btn"
                                title="Open Google Mail in new tab with pre-filled content"
                            >
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                </svg>
                                Open in Google Mail
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Beautiful Prebuilt Response Templates --}}
                <div class="mmn-template-bar">
                    <span style="font-size: 12px; font-weight: 800; color: #166534; margin-right: 2px;">⚡ Prebuilt Templates:</span>
                    <button type="button" wire:click="applyPrebuiltTemplate('academic_doubt')" class="mmn-template-pill">
                        🎓 Academic Doubt
                    </button>
                    <button type="button" wire:click="applyPrebuiltTemplate('dns_sponsorship')" class="mmn-template-pill">
                        ⚓ Sponsorship Counseling
                    </button>
                    <button type="button" wire:click="applyPrebuiltTemplate('batch_enrollment')" class="mmn-template-pill">
                        📦 Batch Enrollment
                    </button>
                    <button type="button" wire:click="applyPrebuiltTemplate('fee_payment')" class="mmn-template-pill">
                        💳 Fee & Payment
                    </button>
                    <button type="button" wire:click="applyPrebuiltTemplate('test_analysis')" class="mmn-template-pill">
                        📝 Test Analysis
                    </button>
                </div>

                {{-- Compose Form with Manual Email Entry Option --}}
                <div class="mmn-email-form">
                    {{-- Editable To Field --}}
                    <div class="mmn-form-row">
                        <span class="mmn-form-label">To (Email):</span>
                        <input
                            type="email"
                            wire:model.live.debounce.300ms="recipientEmail"
                            placeholder="Type student email address manually (e.g. student@gmail.com)..."
                            class="mmn-form-input"
                            style="font-weight: 600;"
                            required
                        />
                    </div>

                    {{-- Student Name Field --}}
                    <div class="mmn-form-row">
                        <span class="mmn-form-label">Student Name:</span>
                        <input
                            type="text"
                            wire:model="recipientName"
                            placeholder="Student Full Name (optional)..."
                            class="mmn-form-input"
                        />
                    </div>

                    {{-- Subject Line --}}
                    <div class="mmn-form-row">
                        <span class="mmn-form-label">Subject:</span>
                        <input
                            type="text"
                            wire:model="emailSubject"
                            class="mmn-form-input"
                            placeholder="Email Subject Line..."
                        />
                    </div>

                    {{-- Body Textarea --}}
                    <textarea
                        wire:model="emailBody"
                        class="mmn-textarea"
                        placeholder="Type your email message to the student here or choose a prebuilt response template above..."
                    ></textarea>
                </div>

                {{-- Email Action Footer --}}
                <div class="mmn-email-footer">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 12px; color: #64748b; font-weight: 600;">Status:</span>
                        <select wire:model="emailStatus" class="mmn-select">
                            <option value="resolved">Mark Resolved</option>
                            <option value="in_progress">In Progress</option>
                            <option value="open">Keep Open</option>
                        </select>

                        @if ($selectedQueryId)
                            <button
                                type="button"
                                wire:click="$set('activeTab', 'whatsapp')"
                                style="font-size: 12px; color: #00a884; font-weight: 700; background: transparent; border: 1px solid #00a884; border-radius: 6px; padding: 4px 10px; cursor: pointer;"
                            >
                                💬 Switch to WhatsApp
                            </button>
                        @endif
                    </div>

                    <button
                        type="button"
                        wire:click="sendPlatformEmail"
                        style="background: #2563eb; color: #ffffff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(37,99,235,0.3);"
                    >
                        <span>Send Email via Brevo to Student</span>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
