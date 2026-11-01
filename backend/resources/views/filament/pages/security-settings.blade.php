<x-filament-panels::page>
    @php $checks = $this->checks(); $bad = collect($checks)->where('ok', false)->count(); @endphp

    @if ($bad === 0)
        <x-filament::callout color="success" icon="heroicon-o-shield-check" heading="All server security checks pass" />
    @else
        <x-filament::callout color="danger" icon="heroicon-o-shield-exclamation" heading="{{ $bad }} security {{ $bad === 1 ? 'setting needs' : 'settings need' }} your attention">
            <x-slot name="description">Fix the red items below in the server's .env file (Hostinger File Manager), then run the update command so the change takes effect.</x-slot>
        </x-filament::callout>
    @endif

    <div class="mmn-two-col">
        <div class="mmn-stack" style="margin-top:0">
            <form wire:submit="save" class="mmn-stack" style="margin-top:0">
                {{ $this->form }}
                <div class="mmn-actions">
                    <x-filament::button type="submit" icon="heroicon-o-check">Save security settings</x-filament::button>
                </div>
            </form>

            <div class="mmn-card">
                <div class="mmn-stat-label">Admin accounts</div>
                <div class="mmn-table-wrap">
                    <table class="mmn-table mmn-table-wide">
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Two-step</th></tr></thead>
                        <tbody>
                        @foreach ($this->admins() as $a)
                            <tr>
                                <td>{{ $a->name }}</td>
                                <td>{{ $a->email }}</td>
                                <td>{{ $a->role?->label() }}</td>
                                <td>
                                    @if ($a->hasTwoFactor())
                                        <x-filament::badge color="success">On</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning">Not set up yet</x-filament::badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mmn-muted">An admin who lost their phone: open Settings &gt; Admins &gt; Reset two-step. They set it up again at their next login.</p>
            </div>
        </div>

        <aside class="mmn-side">
            <div class="mmn-card">
                <div class="mmn-stat-label">Server security checklist</div>
                <ul class="mmn-check-list">
                    @foreach ($checks as $c)
                        <li class="{{ $c['ok'] ? 'ok' : 'bad' }}">
                            <span class="dot"></span>
                            <div><strong>{{ $c['title'] }}</strong>@unless ($c['ok'])<small>{{ $c['hint'] }}</small>@endunless</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </div>
</x-filament-panels::page>
