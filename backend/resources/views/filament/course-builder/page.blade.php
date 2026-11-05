<x-filament-panels::page>
    {{--
        Course builder layout. Logic lives in app/Filament/Resources/CourseResource/Pages/ManageCurriculum.php.
        Sections: summary strip → outline (subjects / mock tests) → add subject / add mock test.
    --}}
    <div
        class="cb-wrap"
        x-data="{ closed: {} }"
        x-on:builder-focus.window="
            const d = $event.detail?.target ? $event.detail : ($event.detail?.[0] ?? {});
            $nextTick(() => setTimeout(() => {
                const el = document.getElementById(d.target);
                if (!el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.remove('cb-flash'); void el.offsetWidth; el.classList.add('cb-flash');
                el.querySelector('[data-add-content]')?.querySelector('button')?.focus();
            }, 150));
        "
    >
        {{-- ===== Summary ===== --}}
        <div class="cb-card cb-stats">
            <span class="cb-stat"><b>{{ $stats['subjects'] }}</b><span class="cb-muted">subjects</span></span>
            <span class="cb-stat"><b>{{ $stats['chapters'] }}</b><span class="cb-muted">chapters</span></span>
            <span class="cb-stat"><b>{{ $stats['contents'] }}</b><span class="cb-muted">content items</span></span>
            <span class="cb-stat"><b>{{ $stats['mockTests'] }}</b><span class="cb-muted">mock tests</span></span>
            @if ($stats['emptyChapters'])
                <span class="cb-warn">⚠ {{ $stats['emptyChapters'] }} empty {{ \Illuminate\Support\Str::plural('chapter', $stats['emptyChapters']) }}</span>
            @endif
            <span style="margin-left:auto" class="cb-row">
                <x-filament::button size="sm" color="gray" x-on:click="closed = {}">Expand all</x-filament::button>
                <x-filament::button size="sm" color="gray"
                    x-on:click="document.querySelectorAll('[data-subject-id]').forEach(e => closed[e.dataset.subjectId] = true)">Collapse all</x-filament::button>
            </span>
        </div>

        {{-- ===== Outline ===== --}}
        @if ($entries->isEmpty())
            <div class="cb-card cb-help">
                <h3 class="cb-title" style="font-size:1.125rem">Let's build this course</h3>
                <p class="cb-muted">A course is a list of subjects, with mock tests wherever you want them.</p>
                <ol class="cb-muted" style="font-size:.9rem">
                    <li>Add a <b>subject</b> below (for example “Physics”).</li>
                    <li>Add <b>chapters</b> to it.</li>
                    <li>Add <b>content</b> to each chapter: lectures, PDFs, notes, live classes, assignments, practice tests.</li>
                    <li>Add a <b>mock test</b> after any subject, or between the chapters inside a subject.</li>
                </ol>
            </div>
        @endif

        <ul
            class="cb-wrap" style="gap:.75rem"
            x-sortable
            x-on:end.stop="$wire.reorderEntries($event.to.sortable.toArray())"
        >
            @php($subjectNo = 0)
            @foreach ($entries as $entry)
                @php($item = $entry->item)
                @if ($item instanceof \App\Models\Subject)
                    @php($subjectNo++)
                    <li
                        wire:key="entry-{{ $entry->id }}"
                        x-sortable-item="entry-{{ $entry->id }}"
                        id="subject-{{ $item->id }}"
                        data-subject-id="{{ $item->id }}"
                        class="cb-card cb-subject"
                    >
                        <div class="cb-head cb-row">
                            <span class="cb-handle" x-sortable-handle title="Drag to reorder"><x-filament::icon icon="heroicon-m-bars-3" /></span>
                            <button type="button" class="cb-collapse" :class="closed[{{ $item->id }}] && 'is-closed'"
                                x-on:click="closed[{{ $item->id }}] = !closed[{{ $item->id }}]" aria-label="Collapse or expand">
                                <x-filament::icon icon="heroicon-m-chevron-down" />
                            </button>
                            <span class="cb-num">Subject {{ $subjectNo }}</span>
                            <div class="cb-grow">
                                <div class="cb-title">{{ $item->title }}</div>
                                @if ($item->description)<div class="cb-muted" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $item->description }}</div>@endif
                            </div>
                            <span class="cb-muted" style="white-space:nowrap">{{ $item->chapters->reject->isMockTest()->count() }} ch · {{ $item->chapters->sum(fn ($c) => $c->contents->count()) }} items</span>
                            <x-filament::button size="xs" color="gray" icon="heroicon-m-pencil-square"
                                wire:click="mountAction('subject', { id: {{ $item->id }} })">Edit</x-filament::button>
                            <x-filament::dropdown placement="bottom-end">
                                <x-slot name="trigger"><x-filament::icon-button icon="heroicon-m-ellipsis-vertical" color="gray" label="More" /></x-slot>
                                <x-filament::dropdown.list>
                                    <x-filament::dropdown.list.item icon="heroicon-m-clipboard-document-list"
                                        wire:click="mountAction('mockTest', { after: {{ $entry->id }} })">Add a mock test after this subject</x-filament::dropdown.list.item>
                                    <x-filament::dropdown.list.item icon="heroicon-m-trash" color="danger"
                                        wire:click="removeEntry({{ $entry->id }})"
                                        wire:confirm="Delete subject “{{ $item->title }}” with all its chapters and content? This cannot be undone.">Delete subject</x-filament::dropdown.list.item>
                                </x-filament::dropdown.list>
                            </x-filament::dropdown>
                        </div>

                        <div class="cb-body" x-show="!closed[{{ $item->id }}]" x-collapse>
                            {{-- Chapters --}}
                            <ul
                                class="cb-chapters"
                                x-sortable x-sortable-group="chapters"
                                data-subject="{{ $item->id }}"
                                x-on:end.stop="$wire.arrangeChapters($event.from.dataset.subject, $event.from.sortable.toArray()); if ($event.to !== $event.from) $wire.arrangeChapters($event.to.dataset.subject, $event.to.sortable.toArray())"
                            >
                                @php($chapterNo = 0)
                                @foreach ($item->chapters as $chapter)
                                  @if ($chapter->isMockTest())
                                    {{-- A mock test placed between chapters: same list, same drag handle --}}
                                    @php($mock = $chapter->test)
                                    <li wire:key="chapter-{{ $chapter->id }}" x-sortable-item="chapter-{{ $chapter->id }}" id="chapter-{{ $chapter->id }}" class="cb-chapter cb-mock">
                                        <div class="cb-head cb-row">
                                            <span class="cb-handle" x-sortable-handle title="Drag to reorder"><x-filament::icon icon="heroicon-m-bars-3" /></span>
                                            <span class="cb-ico t-mock"><x-filament::icon icon="heroicon-o-clipboard-document-list" /></span>
                                            <div class="cb-grow">
                                                <div class="cb-title">{{ $mock?->title ?? $chapter->title }}</div>
                                                <div class="cb-muted">Mock test
                                                    @if ($mock) · {{ $mock->questions_count }} {{ \Illuminate\Support\Str::plural('question', $mock->questions_count) }} · {{ $mock->duration_minutes }} min
                                                        @unless ($mock->is_published) · <span class="cb-warn">draft (hidden from students)</span>@endunless
                                                        @if ($mock->questions_count === 0) · <span class="cb-warn">no questions yet</span>@endif
                                                    @endif
                                                </div>
                                            </div>
                                            @if ($mock)
                                                <x-filament::button tag="a" size="xs" color="success" icon="heroicon-m-queue-list"
                                                    :href="\App\Filament\Resources\TestResource::getUrl('questions', ['record' => $mock])">Manage questions</x-filament::button>
                                                <x-filament::button tag="a" size="xs" color="gray" icon="heroicon-m-cog-6-tooth"
                                                    :href="\App\Filament\Resources\TestResource::getUrl('edit', ['record' => $mock])">Settings</x-filament::button>
                                            @endif
                                            <x-filament::icon-button icon="heroicon-m-x-mark" color="danger" size="sm" label="Remove from subject"
                                                wire:click="deleteChapter({{ $chapter->id }})"
                                                wire:confirm="Take this mock test out of the subject? The test and its results are kept." />
                                        </div>
                                    </li>
                                  @else
                                    @php($chapterNo++)
                                    <li wire:key="chapter-{{ $chapter->id }}" x-sortable-item="chapter-{{ $chapter->id }}" id="chapter-{{ $chapter->id }}" class="cb-chapter">
                                        <div class="cb-head cb-row">
                                            <span class="cb-handle" x-sortable-handle title="Drag to reorder"><x-filament::icon icon="heroicon-m-bars-3" /></span>
                                            <span class="cb-muted" style="font-weight:600">Chapter {{ $chapterNo }}</span>
                                            <span class="cb-title cb-grow">{{ $chapter->title }}</span>
                                            <span class="cb-muted">{{ $chapter->contents->count() }} {{ \Illuminate\Support\Str::plural('item', $chapter->contents->count()) }}</span>
                                            <x-filament::icon-button icon="heroicon-m-pencil-square" color="gray" size="sm" label="Rename chapter"
                                                wire:click="mountAction('chapter', { id: {{ $chapter->id }} })" />
                                            <x-filament::dropdown placement="bottom-end">
                                                <x-slot name="trigger"><x-filament::icon-button icon="heroicon-m-ellipsis-vertical" color="gray" size="sm" label="More" /></x-slot>
                                                <x-filament::dropdown.list>
                                                    <x-filament::dropdown.list.item icon="heroicon-m-clipboard-document-list"
                                                        wire:click="mountAction('mockTest', { subject: {{ $item->id }}, afterChapter: {{ $chapter->id }} })">Add a mock test after this chapter</x-filament::dropdown.list.item>
                                                </x-filament::dropdown.list>
                                            </x-filament::dropdown>
                                            <x-filament::icon-button icon="heroicon-m-trash" color="danger" size="sm" label="Delete chapter"
                                                wire:click="deleteChapter({{ $chapter->id }})"
                                                wire:confirm="Delete chapter “{{ $chapter->title }}” and everything in it?" />
                                        </div>

                                        {{-- Contents (drag between chapters is allowed) --}}
                                        <ul
                                            class="cb-contents"
                                            x-sortable x-sortable-group="contents"
                                            data-chapter="{{ $chapter->id }}"
                                            x-on:end.stop="$wire.arrangeContents($event.from.dataset.chapter, $event.from.sortable.toArray()); if ($event.to !== $event.from) $wire.arrangeContents($event.to.dataset.chapter, $event.to.sortable.toArray())"
                                        >
                                            @foreach ($chapter->contents as $content)
                                                <li wire:key="content-{{ $content->id }}" x-sortable-item="content-{{ $content->id }}" class="cb-content">
                                                    <span class="cb-handle" x-sortable-handle title="Drag to reorder or move to another chapter"><x-filament::icon icon="heroicon-m-bars-3" /></span>
                                                    <span class="cb-ico t-{{ $content->type->value }}"><x-filament::icon :icon="\App\Filament\Support\ContentForm::ICONS[$content->type->value]" /></span>
                                                    <div class="cb-grow">
                                                        <div class="cb-title" style="font-size:.875rem">{{ $content->title }}</div>
                                                        <div class="cb-muted cb-math"><b style="font-weight:500">{{ $content->type->label() }}</b>@if ($meta = $outline->describe($content)) · {{ $meta }}@endif</div>
                                                    </div>
                                                    @if ($content->type === \App\Enums\ContentType::PracticeTest && $content->contentable)
                                                        <x-filament::button tag="a" size="xs" color="success" icon="heroicon-m-queue-list"
                                                            :href="\App\Filament\Resources\TestResource::getUrl('questions', ['record' => $content->contentable])">Manage questions</x-filament::button>
                                                    @endif
                                                    <x-filament::button size="xs" color="gray" icon="heroicon-m-pencil-square"
                                                        wire:click="mountAction('content', { id: {{ $content->id }}, type: '{{ $content->type->value }}' })">Edit</x-filament::button>
                                                    <x-filament::dropdown placement="bottom-end">
                                                        <x-slot name="trigger"><x-filament::icon-button icon="heroicon-m-ellipsis-vertical" color="gray" size="sm" label="More" /></x-slot>
                                                        <x-filament::dropdown.list>
                                                            <x-filament::dropdown.list.item icon="heroicon-m-arrow-right-circle"
                                                                wire:click="mountAction('moveContent', { id: {{ $content->id }} })">Move to another chapter</x-filament::dropdown.list.item>
                                                            <x-filament::dropdown.list.item icon="heroicon-m-trash" color="danger"
                                                                wire:click="deleteContent({{ $content->id }})"
                                                                wire:confirm="Remove “{{ $content->title }}” from this chapter?">Delete</x-filament::dropdown.list.item>
                                                        </x-filament::dropdown.list>
                                                    </x-filament::dropdown>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if ($chapter->contents->isEmpty())
                                            <div class="cb-empty" style="margin:0 .625rem .25rem">This chapter is empty. Add its first content below.</div>
                                        @endif

                                        {{-- Always-visible "Add content" under every chapter --}}
                                        <div class="cb-footer" data-add-content>
                                            <x-filament::dropdown placement="bottom-start">
                                                <x-slot name="trigger">
                                                    <x-filament::button size="sm" icon="heroicon-m-plus">Add content</x-filament::button>
                                                </x-slot>
                                                <x-filament::dropdown.list>
                                                    @foreach ($contentTypes as $type)
                                                        <x-filament::dropdown.list.item :icon="$type['icon']"
                                                            wire:click="mountAction('content', { chapter: {{ $chapter->id }}, type: '{{ $type['value'] }}' })">{{ $type['label'] }}</x-filament::dropdown.list.item>
                                                    @endforeach
                                                    <x-filament::dropdown.list.item icon="heroicon-m-arrow-down-tray"
                                                        wire:click="mountAction('bulkDriveImport', { chapter: {{ $chapter->id }} })">Bulk import from Google Drive</x-filament::dropdown.list.item>
                                                </x-filament::dropdown.list>
                                            </x-filament::dropdown>
                                        </div>
                                    </li>
                                  @endif
                                @endforeach
                            </ul>

                            {{-- Add chapter (type a name, press Enter), or a mock test between the chapters --}}
                            <div class="cb-add">
                                <input class="cb-input" type="text" placeholder="New chapter name — press Enter to add"
                                    wire:model="newChapter.{{ $item->id }}" wire:keydown.enter="addChapter({{ $item->id }})">
                                <x-filament::button color="gray" icon="heroicon-m-plus" wire:click="addChapter({{ $item->id }})">Add chapter</x-filament::button>
                                <x-filament::button color="gray" icon="heroicon-m-clipboard-document-list"
                                    wire:click="mountAction('mockTest', { subject: {{ $item->id }} })">Add mock test</x-filament::button>
                            </div>
                        </div>
                    </li>
                @elseif ($item instanceof \App\Models\Test)
                    <li wire:key="entry-{{ $entry->id }}" x-sortable-item="entry-{{ $entry->id }}" class="cb-card cb-mock">
                        <div class="cb-head cb-row">
                            <span class="cb-handle" x-sortable-handle title="Drag to reorder"><x-filament::icon icon="heroicon-m-bars-3" /></span>
                            <span class="cb-ico t-mock"><x-filament::icon icon="heroicon-o-clipboard-document-list" /></span>
                            <div class="cb-grow">
                                <div class="cb-title">{{ $item->title }}</div>
                                <div class="cb-muted">Mock test · {{ $item->questions_count }} {{ \Illuminate\Support\Str::plural('question', $item->questions_count) }} · {{ $item->duration_minutes }} min
                                    @unless ($item->is_published) · <span class="cb-warn">draft (hidden from students)</span>@endunless
                                    @if ($item->questions_count === 0) · <span class="cb-warn">no questions yet</span>@endif
                                </div>
                            </div>
                            <x-filament::button tag="a" size="xs" color="success" icon="heroicon-m-queue-list"
                                :href="\App\Filament\Resources\TestResource::getUrl('questions', ['record' => $item])">Manage questions</x-filament::button>
                            <x-filament::button tag="a" size="xs" color="gray" icon="heroicon-m-cog-6-tooth"
                                :href="\App\Filament\Resources\TestResource::getUrl('edit', ['record' => $item])">Settings</x-filament::button>
                            <x-filament::dropdown placement="bottom-end">
                                <x-slot name="trigger"><x-filament::icon-button icon="heroicon-m-ellipsis-vertical" color="gray" size="sm" label="More" /></x-slot>
                                <x-filament::dropdown.list>
                                    <x-filament::dropdown.list.item icon="heroicon-m-x-mark" color="danger"
                                        wire:click="removeEntry({{ $entry->id }})"
                                        wire:confirm="Take this mock test off the course page? The test and its results are kept.">Remove from course</x-filament::dropdown.list.item>
                                </x-filament::dropdown.list>
                            </x-filament::dropdown>
                        </div>
                    </li>
                @endif
            @endforeach
        </ul>

        {{-- ===== Add subject / mock test ===== --}}
        <div class="cb-card" style="padding:1rem">
            <div class="cb-add">
                <input class="cb-input" type="text" placeholder="New subject name — press Enter to add" wire:model="newSubject" wire:keydown.enter="addSubject">
                <x-filament::button icon="heroicon-m-plus" wire:click="addSubject">Add subject</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-clipboard-document-list" wire:click="mountAction('mockTest')">Add mock test</x-filament::button>
            </div>
            <p class="cb-muted" style="margin-top:.5rem">Tip: add a chapter (or mock test), then drag its ☰ handle to put it between any existing chapters. Drag a content item into another chapter to move it. A chapter's ⋮ menu inserts a mock test right after it.</p>
        </div>
    </div>
</x-filament-panels::page>
