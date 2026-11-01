<x-filament-panels::page>
    @if ($this->connected())
        <x-filament::callout color="success" icon="heroicon-o-check-circle" heading="Razorpay is linked ({{ $this->mode() }} mode)">
            <x-slot name="description">Payments below are read live from Razorpay. Coupons you create under Sales &gt; Coupons change the amount charged at checkout.</x-slot>
        </x-filament::callout>
    @else
        <x-filament::callout color="warning" icon="heroicon-o-link" heading="Link your Razorpay account">
            <x-slot name="description">Add your API keys to see recent payments and to find a payment that did not give the student a course.</x-slot>
        </x-filament::callout>
    @endif

    <form wire:submit="saveKeys" class="mmn-stack">
        {{ $this->form }}

        <div class="mmn-actions">
            <x-filament::button type="submit" icon="heroicon-o-check">Save &amp; test keys</x-filament::button>
            <x-filament::button type="button" color="gray" icon="heroicon-o-magnifying-glass" wire:click="lookup" wire:loading.attr="disabled">Find payment</x-filament::button>
            <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-path" wire:click="refreshPayments" wire:loading.attr="disabled">Refresh payments</x-filament::button>
        </div>
    </form>

    @if ($foundError)
        <x-filament::callout color="danger" icon="heroicon-o-exclamation-triangle" heading="{{ $foundError }}" />
    @endif

    @if ($found)
        <div class="mmn-card">
            <div class="mmn-stat-label">Razorpay says</div>
            <table class="mmn-table">
                <tr><th>ID</th><td><code>{{ $found['id'] }}</code></td></tr>
                <tr><th>Status</th><td>{{ ucfirst($found['status']) }}</td></tr>
                <tr><th>Amount</th><td>₹{{ number_format($found['amount'], 2) }}</td></tr>
                <tr><th>Student</th><td>{{ $found['email'] ?: '—' }} {{ $found['contact'] }}</td></tr>
                @if ($found['error'])<tr><th>Failure reason</th><td>{{ $found['error'] }}</td></tr>@endif
                <tr><th>Course given in LMS</th><td>
                    @if ($found['course_given'])
                        <x-filament::badge color="success">Yes</x-filament::badge>
                    @else
                        <x-filament::badge color="danger">No, check the WordPress webhook log</x-filament::badge>
                    @endif
                </td></tr>
            </table>
        </div>
    @endif

    @if ($this->connected())
        <div class="mmn-card">
            <div class="mmn-stat-label">Latest 25 payments</div>
            @if ($paymentsError)
                <p class="mmn-muted">{{ $paymentsError }}</p>
            @elseif (empty($payments))
                <p class="mmn-muted">No payments yet.</p>
            @else
                <div class="mmn-table-wrap">
                    <table class="mmn-table mmn-table-wide">
                        <thead><tr><th>When</th><th>Student</th><th>Amount</th><th>Razorpay status</th><th>Course given</th><th>Payment ID</th></tr></thead>
                        <tbody>
                        @foreach ($payments as $p)
                            <tr>
                                <td>{{ $p['created'] }}</td>
                                <td>{{ $p['email'] ?: '—' }}<br><span class="mmn-muted">{{ $p['contact'] }}</span></td>
                                <td>₹{{ number_format($p['amount'], 2) }}</td>
                                <td><x-filament::badge :color="match($p['status']) { 'captured' => 'success', 'failed' => 'danger', 'authorized' => 'warning', default => 'gray' }">{{ ucfirst($p['status']) }}</x-filament::badge></td>
                                <td>
                                    @if ($p['course_given'])
                                        <x-filament::badge color="success">Yes</x-filament::badge>
                                    @elseif ($p['status'] === 'captured')
                                        <x-filament::badge color="danger">Missing</x-filament::badge>
                                    @else
                                        <span class="mmn-muted">—</span>
                                    @endif
                                </td>
                                <td><code>{{ $p['id'] }}</code></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
