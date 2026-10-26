<x-filament-panels::page>
    {{-- Logic: app/Filament/Resources/TestResource/Pages/ManageQuestions.php --}}
    @php($total = $inTest->count())
    <div class="cb-wrap">
        {{-- ===== Summary ===== --}}
        <div class="cb-card cb-stats">
            <span class="cb-stat"><b>{{ $total }}</b><span class="cb-muted">/ {{ $max }} questions</span></span>
            <span class="cb-stat"><b>{{ rtrim(rtrim(number_format($total * $test->marks_correct, 2), '0'), '.') }}</b><span class="cb-muted">total marks</span></span>
            <span class="cb-stat"><b>{{ $test->duration_minutes }}</b><span class="cb-muted">minutes</span></span>
            <span class="cb-muted">+{{ $test->marks_correct + 0 }} / −{{ $test->marks_wrong + 0 }} per question</span>
            <span class="cb-muted">{{ $test->kind === \App\Enums\TestKind::Mock ? 'Mock test' : 'Practice test' }} · {{ $test->course->title }}</span>
            @if ($total === 0)<span class="cb-warn">⚠ no questions yet: add some from the question bank on the right</span>@endif
        </div>

        <div class="qb-grid">
            {{-- ===== Questions in this test ===== --}}
            <div class="cb-card" style="padding:1rem">
                <div class="cb-row" style="margin-bottom:.75rem">
                    <h3 class="cb-title" style="font-size:1rem">Questions in this test</h3>
                    <span class="cb-muted">Drag ⋮⋮ to reorder</span>
                </div>

                @if ($total === 0)
                    <div class="cb-empty" style="padding:2rem">
                        Nothing here yet.<br>Add questions from the bank on the right, or use “Write a new question” at the top.
                    </div>
                @endif

                <ul class="qb-list" x-sortable x-on:end.stop="$wire.reorder($event.to.sortable.toArray())">
                    @foreach ($inTest as $question)
                        <li wire:key="q-{{ $question->id }}" x-sortable-item="q-{{ $question->id }}" class="qb-q">
                            <span class="cb-handle" x-sortable-handle title="Drag to reorder"><x-filament::icon icon="heroicon-m-bars-3" /></span>
                            <span class="qb-no">{{ $loop->iteration }}</span>
                            <div class="cb-grow">
                                <div class="qb-text cb-math">{!! $question->body !!}</div>
                                <div class="qb-tags">
                                    <x-filament::badge color="success" size="sm">Answer: {{ $service->correctLetter($question) }}</x-filament::badge>
                                    @if ($question->difficulty)<x-filament::badge color="gray" size="sm">{{ ucfirst($question->difficulty) }}</x-filament::badge>@endif
                                    @if ($question->tests_count > 1)<span class="cb-muted">used in {{ $question->tests_count }} tests</span>@endif
                                    <span class="cb-muted">#{{ $question->id }}</span>
                                </div>
                            </div>
                            <div class="cb-row" style="flex:none;gap:.25rem">
                                <x-filament::icon-button icon="heroicon-m-eye" color="gray" size="sm" label="Preview question" tooltip="Preview with the answer and explanation"
                                    wire:click="mountAction('previewQuestion', { id: {{ $question->id }} })" />
                                <x-filament::icon-button icon="heroicon-m-pencil-square" color="gray" size="sm" label="Edit question" tooltip="Edit"
                                    wire:click="mountAction('editQuestion', { id: {{ $question->id }} })" />
                                <x-filament::icon-button icon="heroicon-m-arrows-right-left" color="gray" size="sm" label="Replace question" tooltip="Replace with another question"
                                    wire:click="mountAction('replaceQuestion', { id: {{ $question->id }} })" />
                                <x-filament::icon-button icon="heroicon-m-x-mark" color="danger" size="sm" label="Remove from test" tooltip="Remove from this test"
                                    wire:click="removeQuestion({{ $question->id }})" wire:confirm="Remove question {{ $loop->iteration }} from this test? It stays in the question bank." />
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- ===== Question bank ===== --}}
            <div class="cb-card qb-sticky">
                <div style="padding:1rem 1rem 0">
                    <div class="cb-row">
                        <h3 class="cb-title" style="font-size:1rem">Question bank</h3>
                        <span class="cb-muted">{{ $bankTotal }} {{ \Illuminate\Support\Str::plural('question', $bankTotal) }} found</span>
                        <a class="cb-muted" style="margin-left:auto;text-decoration:underline" href="{{ \App\Filament\Resources\QuestionResource::getUrl('index') }}" target="_blank">Open full bank ↗</a>
                    </div>
                </div>
                <div class="qb-filters">
                    <div class="full">
                        <x-filament::input.wrapper inline-prefix prefix-icon="heroicon-m-magnifying-glass">
                            <x-filament::input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by words, an option, or question number" />
                        </x-filament::input.wrapper>
                    </div>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="difficulty">
                            <option value="">Any difficulty</option>
                            <option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="subject">
                            <option value="">Any subject</option>
                            @foreach ($subjects as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <label class="cb-muted full cb-row" style="gap:.5rem">
                        <x-filament::input.checkbox wire:model.live="hideAdded" /> Hide questions already in this test
                    </label>
                </div>

                <div style="padding:0 1rem 1rem">
                    @if (count($selected))
                        <div class="cb-row" style="margin-bottom:.5rem">
                            <x-filament::button wire:click="addSelected" icon="heroicon-m-plus">Add {{ count($selected) }} selected</x-filament::button>
                            <x-filament::button color="gray" wire:click="$set('selected', [])">Clear</x-filament::button>
                        </div>
                    @endif

                    <div class="qb-list qb-scroll">
                        @forelse ($bank as $question)
                            @php($added = in_array($question->id, $inTestIds))
                            <div wire:key="bank-{{ $question->id }}" class="qb-q" style="{{ $added ? 'opacity:.6' : '' }}">
                                @unless ($added)
                                    <x-filament::input.checkbox wire:model.live="selected" value="{{ $question->id }}" style="margin-top:.35rem" />
                                @endunless
                                <div class="cb-grow">
                                    <div class="qb-text cb-math">{!! $question->body !!}</div>
                                    <div class="qb-tags">
                                        <x-filament::badge color="success" size="sm">Answer: {{ $service->correctLetter($question) }}</x-filament::badge>
                                        @if ($question->difficulty)<x-filament::badge color="gray" size="sm">{{ ucfirst($question->difficulty) }}</x-filament::badge>@endif
                                        <span class="cb-muted">in {{ $question->tests_count }} {{ \Illuminate\Support\Str::plural('test', $question->tests_count) }} · #{{ $question->id }}</span>
                                    </div>
                                </div>
                                <x-filament::icon-button icon="heroicon-m-eye" color="gray" size="sm" label="Preview question" tooltip="Preview with the answer and explanation"
                                    wire:click="mountAction('previewQuestion', { id: {{ $question->id }} })" />
                                @if ($added)
                                    <x-filament::badge color="info" size="sm">In test</x-filament::badge>
                                @else
                                    <x-filament::button size="xs" icon="heroicon-m-plus" wire:click="addQuestion({{ $question->id }})">Add</x-filament::button>
                                @endif
                            </div>
                        @empty
                            <div class="cb-empty" style="padding:1.5rem">No questions match. Change the search, or write a new question.</div>
                        @endforelse

                        @if ($bankTotal > $bank->count())
                            <x-filament::button color="gray" wire:click="loadMore" style="align-self:center">Show more ({{ $bankTotal - $bank->count() }} left)</x-filament::button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
