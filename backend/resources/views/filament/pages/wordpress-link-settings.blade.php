<x-filament-panels::page>
    @php $st = $this->status(); @endphp

    @if ($st['set'] && $st['strong'])
        <x-filament::callout color="success" icon="heroicon-o-check-circle" heading="The WordPress link is set up and the secret is strong">
            <x-slot name="description">Secret kept in: {{ $st['source'] }}. Payments and "go to my course" links from WordPress are being accepted.</x-slot>
        </x-filament::callout>
    @elseif ($st['set'])
        <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" heading="The secret works but is too short">
            <x-slot name="description">Secret kept in: {{ $st['source'] }}. Make a strong one below (48 random characters) and paste it into WordPress.</x-slot>
        </x-filament::callout>
    @else
        <x-filament::callout color="danger" icon="heroicon-o-x-circle" heading="The WordPress link is switched off">
            <x-slot name="description">No secret yet, so payments from WordPress are refused. Make one below and paste it into WordPress.</x-slot>
        </x-filament::callout>
    @endif

    @if ($newSecret)
        <div class="mmn-card" x-data="{ copied: false }" style="border-color:#f59e0b">
            <div class="mmn-stat-label" style="color:#b45309">Your new secret (shown only once)</div>
            <p class="mmn-muted">Copy it now, paste it in WordPress, then close this box. Until WordPress has the same secret, payments are refused.</p>
            <div class="mmn-actions" style="border-top:0;padding-top:.75rem">
                <input type="text" readonly value="{{ $newSecret }}" x-ref="s" onclick="this.select()"
                       style="flex:1 1 22rem;font-family:ui-monospace,Menlo,monospace;font-size:.9rem;padding:.6rem .75rem;border:1px solid var(--gray-300);border-radius:.625rem;background:#fffbeb;color:#0f172a">
                <x-filament::button type="button" icon="heroicon-o-clipboard" x-on:click="navigator.clipboard.writeText($refs.s.value); copied = true">
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="dismiss">I have pasted it, close</x-filament::button>
            </div>
        </div>
    @endif

    <div class="mmn-two-col">
        <div class="mmn-card">
            <div class="mmn-stat-label">Make a new secret</div>
            <p class="mmn-muted">A secret is a long random password shared by this site and the WordPress shop. Every payment message is signed with it, so nobody else can hand out courses.
                Making a new one replaces the old one at once, so paste it into WordPress straight away (steps on the right).</p>
            <div class="mmn-actions" style="border-top:0;padding-top:1rem">
                <x-filament::button icon="heroicon-o-key" wire:click="generate"
                    wire:confirm="Make a new secret? The old one stops working immediately, so payments are refused until you paste the new one into WordPress. Continue?">
                    Generate secret
                </x-filament::button>
            </div>
        </div>

        <aside class="mmn-side">
            <div class="mmn-card">
                <div class="mmn-stat-label">Then in WordPress</div>
                <ol class="mmn-muted" style="padding-left:1.1rem;list-style:decimal;display:flex;flex-direction:column;gap:.4rem">
                    <li>WordPress admin &rarr; <strong>Settings &rarr; LMS link</strong>.</li>
                    <li>API address: <code>{{ $st['api_url'] }}</code></li>
                    <li>Student site: <code>{{ $st['student_url'] }}</code></li>
                    <li>Paste the secret into <strong>Shared secret</strong> and save.</li>
                    <li>Press <strong>Send my own account to the LMS</strong>. "Connected" means it works.</li>
                </ol>
            </div>
        </aside>
    </div>
</x-filament-panels::page>
