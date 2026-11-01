<x-filament-panels::page>
    <x-filament::callout color="info" icon="heroicon-o-information-circle" heading="Two emails go out after every successful payment">
        <x-slot name="description">
            The student gets a confirmation with the course, validity and access link. The admin address(es) get an alert with the
            student's details, amount, order and payment IDs, so a stuck payment can be checked straight away.
        </x-slot>
    </x-filament::callout>

    <form wire:submit="save" class="mmn-stack">
        <div class="mmn-two-col">
            <div>{{ $this->form }}</div>

            <aside class="mmn-side">
                <div class="mmn-card">
                    <div class="mmn-stat-label">Placeholders</div>
                    <p class="mmn-muted">Type these in the subject or message. They are replaced with the real details.</p>
                    <ul class="mmn-chip-list">
                        @foreach ($this->placeholders() as $token => $meaning)
                            <li><code>{{ $token }}</code><span>{{ $meaning }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>

        <div class="mmn-actions">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check">Save purchase emails</x-filament::button>
            <x-filament::button type="button" color="gray" icon="heroicon-o-paper-airplane" wire:click="sendTest" wire:loading.attr="disabled">Send me a test</x-filament::button>
            <x-filament::button type="button" color="danger" outlined icon="heroicon-o-arrow-path" wire:click="resetToDefault"
                wire:confirm="Restore the default wording for both emails? Your changes will be lost.">Restore defaults</x-filament::button>
        </div>
    </form>

    <div class="mmn-two-col mmn-preview-row">
        @foreach (['admin' => 'Admin email preview', 'student' => 'Student email preview'] as $who => $label)
            @php($p = $this->preview($who))
            <div class="mmn-card">
                <div class="mmn-stat-label">{{ $label }} (sample data)</div>
                <p class="mmn-preview-subject">{{ $p['subject'] }}</p>
                <div class="mmn-preview-body">{!! $p['html'] !!}</div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
