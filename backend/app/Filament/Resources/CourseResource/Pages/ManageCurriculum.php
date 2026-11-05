<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Enums\ContentType;
use App\Enums\TestKind;
use App\Filament\Resources\CourseResource;
use App\Filament\Support\ContentForm;
use App\Models\Chapter;
use App\Models\ChapterContent;
use App\Models\CurriculumItem;
use App\Models\Subject;
use App\Models\Test;
use App\Services\Courses\CourseOutline;
use App\Services\Courses\GoogleDriveFolderScanner;
use App\Services\Courses\RemotePdfDownloader;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * THE COURSE BUILDER: an outline of the whole course, edited in place.
 *
 *   Course
 *    ├─ Subject ── Chapter ── Content (video, live class, PDF, notes, assignment, practice test)
 *    └─ Mock test           ← sits anywhere between subjects
 *
 * Everything is a compact row. Drag the ⋮⋮ handle to reorder (contents can be dragged between chapters).
 * Adding/editing opens a slide-over. All changes save immediately via App\Services\Courses\CourseOutline.
 * The page layout is resources/views/filament/course-builder/page.blade.php.
 */
class ManageCurriculum extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.course-builder.page';

    /** "New chapter" text boxes, keyed by subject id. */
    public array $newChapter = [];

    public string $newSubject = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    public function getBreadcrumb(): string
    {
        return 'Build course';
    }

    public function cleanChapterTitle(string $title): string
    {
        $clean = preg_replace('/^(?:Chapter\s*\d+|Ch\s*\d+|\d+)[\s:\.-]*/i', '', $title);

        return filled(trim($clean)) ? trim($clean) : $title;
    }

    protected function getViewData(): array
    {
        $entries = $this->outline()->entries($this->getRecord());

        return [
            'entries' => $entries,
            'stats' => $this->outline()->stats($entries),
            'outline' => $this->outline(),
            'contentTypes' => collect(ContentType::cases())->map(fn (ContentType $t) => ['value' => $t->value, 'label' => $t->label(), 'icon' => ContentForm::ICONS[$t->value]]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('details')->label('Course details')->icon('heroicon-o-pencil-square')->color('gray')
                ->url(fn () => CourseResource::getUrl('edit', ['record' => $this->getRecord()])),
            Action::make('students')->label('Students')->icon('heroicon-o-user-group')->color('gray')
                ->url(fn () => CourseResource::getUrl('edit', ['record' => $this->getRecord()]).'#relation-manager'),
        ];
    }

    protected function getActions(): array
    {
        return [
            $this->contentAction(),
            $this->bulkDriveImportAction(),
            $this->importChapterFolderAction(),
            $this->importSubjectFolderAction(),
            $this->importCourseFolderAction(),
            $this->subjectAction(),
            $this->chapterAction(),
            $this->mockTestAction(),
            $this->moveContentAction(),
        ];
    }

    // ------------------------------------------------------------------
    // Modals (opened with mountAction('name', {...}) from the Blade view)
    // ------------------------------------------------------------------

    /** Add or edit one content item. arguments: chapter, type, id (when editing) */
    public function contentAction(): Action
    {
        return Action::make('content')
            ->slideOver()->modalWidth('3xl')
            ->modalHeading(fn (array $arguments) => (isset($arguments['id']) ? 'Edit ' : 'Add ').(isset($arguments['type']) ? strtolower(ContentType::tryFrom($arguments['type'])?->label() ?? 'content') : 'content'))
            ->modalSubmitActionLabel(fn (array $arguments) => isset($arguments['id']) ? 'Save changes' : 'Add to chapter')
            ->schema(fn (array $arguments) => isset($arguments['type']) && ContentType::tryFrom($arguments['type']) ? ContentForm::schema(ContentType::from($arguments['type']), $this->getRecord()->id) : [])
            // Editing: fill with the saved values. Adding: fill() with no data so field defaults apply.
            ->mountUsing(fn (Schema $schema, array $arguments) => $schema->fill(
                isset($arguments['id']) ? $this->outline()->contentData($this->findContent($arguments['id'])) : null,
            ))
            ->action(function (array $data, array $arguments) {
                $existing = isset($arguments['id']) ? $this->findContent($arguments['id']) : null;
                $chapter = $existing?->chapter ?? $this->findChapter($arguments['chapter']);

                if ($arguments['type'] === ContentType::PracticeTest->value) {
                    // "Create a new practice test" is turned into a real test (named after the content) before saving.
                    $data['test_id'] = ContentForm::resolveTest($data, $this->getRecord()->id, TestKind::Practice, $data['title'])->id;
                }
                $this->outline()->saveContent($chapter, ContentType::from($arguments['type']), $data, $existing);

                Notification::make()->title($existing ? 'Saved' : 'Added to “'.$chapter->title.'”')->success()->send();
            });
    }

    /** Bulk import multiple PDFs from Google Drive / direct links into a chapter. arguments: chapter */
    public function bulkDriveImportAction(): Action
    {
        return Action::make('bulkDriveImport')
            ->modalHeading('Bulk Import PDFs from Google Drive / Links')
            ->modalDescription('Paste one or more shared Google Drive links, folder links, or direct PDF URLs (one per line). The server downloads each file directly and adds them to this chapter.')
            ->modalSubmitActionLabel('Start Download & Import')
            ->modalWidth('2xl')
            ->schema([
                Textarea::make('urls')
                    ->label('Google Drive Links or PDF URLs (one per line)')
                    ->placeholder("https://drive.google.com/drive/folders/... (folder links automatically expand)\nhttps://drive.google.com/file/d/...\nCustom Title | https://drive.google.com/file/d/...")
                    ->rows(8)
                    ->required()
                    ->helperText('Make sure Google Drive files and folders have General Access set to "Anyone with the link can view". Folder links will automatically expand and download all PDFs inside them.'),
            ])
            ->action(function (array $data, array $arguments) {
                if (empty($arguments['chapter'])) {
                    throw new \Exception('No chapter specified.');
                }
                $chapter = $this->findChapter((int) $arguments['chapter']);
                $lines = preg_split('/[\r\n]+/', trim($data['urls']));
                $count = 0;
                $errors = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    $customTitle = null;
                    $url = $line;
                    if (str_contains($line, '|')) {
                        [$customTitle, $url] = array_map('trim', explode('|', $line, 2));
                    }

                    // Auto-detect and expand Google Drive folder links!
                    if (GoogleDriveFolderScanner::isFolderUrl($url)) {
                        try {
                            $res = GoogleDriveFolderScanner::importIntoChapter($chapter, $url, $this->outline(), true);
                            $count += $res['imported'];
                            if (! empty($res['errors'])) {
                                $errors = array_merge($errors, $res['errors']);
                            }
                        } catch (\Throwable $e) {
                            $errors[] = Str::limit($url, 45).': '.$e->getMessage();
                        }
                        continue;
                    }

                    try {
                        $downloaded = RemotePdfDownloader::download($url);
                        $title = filled($customTitle) ? $customTitle : $downloaded['title'];

                        $this->outline()->saveContent($chapter, ContentType::Pdf, [
                            'title' => $title,
                            'file' => $downloaded['file_path'],
                        ]);
                        $count++;
                    } catch (\Throwable $e) {
                        $errors[] = Str::limit($url, 45).': '.$e->getMessage();
                    }
                }

                if ($count > 0) {
                    Notification::make()->title("Imported {$count} PDF(s) into “{$chapter->title}”")->success()->send();
                }
                if (! empty($errors)) {
                    Notification::make()->title('Some files could not be imported')->body(implode("\n", array_slice($errors, 0, 5)))->warning()->persistent()->send();
                }
            });
    }

    /** Import a Google Drive folder directly into a specific Chapter with file selection. arguments: chapter */
    public function importChapterFolderAction(): Action
    {
        return Action::make('importChapterFolder')
            ->modalHeading(function (array $arguments) {
                if (empty($arguments['chapter'])) {
                    return 'Import Google Drive Folder';
                }
                try {
                    return 'Import Google Drive Folder into “'.$this->findChapter((int) $arguments['chapter'])->title.'”';
                } catch (\Throwable) {
                    return 'Import Google Drive Folder';
                }
            })
            ->modalDescription('Paste a Google Drive folder link. The system will scan the folder and let you choose exactly which PDFs to download into this chapter.')
            ->modalSubmitActionLabel('Start Download & Import')
            ->modalWidth('2xl')
            ->schema([
                TextInput::make('folder_url')
                    ->label('Google Drive Folder Link')
                    ->placeholder('https://drive.google.com/drive/folders/...')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if (empty($state) || ! GoogleDriveFolderScanner::isFolderUrl($state)) {
                            $set('selected_files', []);
                            return;
                        }
                        try {
                            $files = GoogleDriveFolderScanner::getFilesList($state);
                            $set('selected_files', collect($files)->pluck('id')->toArray());
                        } catch (\Throwable) {
                            $set('selected_files', []);
                        }
                    })
                    ->helperText('Paste folder link and click outside to load files. General Access must be "Anyone with the link can view".'),

                CheckboxList::make('selected_files')
                    ->label('Choose PDFs to Import')
                    ->bulkToggleable()
                    ->options(function (Get $get) {
                        $url = $get('folder_url');
                        if (empty($url) || ! GoogleDriveFolderScanner::isFolderUrl($url)) {
                            return [];
                        }
                        try {
                            $files = GoogleDriveFolderScanner::getFilesList($url);
                            return collect($files)->mapWithKeys(function ($f) {
                                $sub = $f['subfolder'] !== 'Root Folder' ? " [{$f['subfolder']}]" : '';
                                return [$f['id'] => "{$f['name']}{$sub}"];
                            })->toArray();
                        } catch (\Throwable $e) {
                            return ['error' => 'Could not read folder: ' . $e->getMessage()];
                        }
                    })
                    ->default(function (Get $get) {
                        $url = $get('folder_url');
                        if (empty($url) || ! GoogleDriveFolderScanner::isFolderUrl($url)) {
                            return [];
                        }
                        try {
                            $files = GoogleDriveFolderScanner::getFilesList($url);
                            return collect($files)->pluck('id')->toArray();
                        } catch (\Throwable) {
                            return [];
                        }
                    })
                    ->visible(fn (Get $get) => filled($get('folder_url')))
                    ->helperText('All files found are checked by default. Uncheck any files you do not want to download.'),
            ])
            ->action(function (array $data, array $arguments) {
                if (empty($arguments['chapter'])) {
                    throw new \Exception('No chapter specified.');
                }
                $chapter = $this->findChapter((int) $arguments['chapter']);
                try {
                    $selectedIds = ! empty($data['selected_files']) ? array_values(array_filter($data['selected_files'], fn ($id) => $id !== 'error')) : null;
                    $res = GoogleDriveFolderScanner::importIntoChapter(
                        $chapter,
                        $data['folder_url'],
                        $this->outline(),
                        $selectedIds
                    );

                    Notification::make()
                        ->title("Imported {$res['imported']} PDF(s) into “{$chapter->title}”")
                        ->body("Downloaded from folder “{$res['folder_title']}”.")
                        ->success()
                        ->send();

                    if (! empty($res['errors'])) {
                        Notification::make()
                            ->title("Some files had warnings ({$res['skipped']} skipped)")
                            ->body(implode("\n", array_slice($res['errors'], 0, 5)))
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Folder import failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /** Import an entire Google Drive folder structure into a Subject with automated 1-19 chapter arrangement. arguments: subject */
    public function importSubjectFolderAction(): Action
    {
        return Action::make('importSubjectFolder')
            ->modalHeading(function (array $arguments) {
                if (empty($arguments['subject'])) {
                    return 'Import Drive Folder into Subject';
                }
                try {
                    return 'Import Drive Folder into “'.$this->findSubject((int) $arguments['subject'])->title.'”';
                } catch (\Throwable) {
                    return 'Import Drive Folder into Subject';
                }
            })
            ->modalDescription('Paste a Google Drive folder link (e.g. containing 19 chapter subfolders). The algorithm automatically arranges chapters 1 to 19, renames them cleanly, organizes materials, and downloads all PDFs.')
            ->modalSubmitActionLabel('Build & Download Curriculum')
            ->modalWidth('2xl')
            ->schema([
                TextInput::make('folder_url')
                    ->label('Google Drive Folder Link')
                    ->placeholder('https://drive.google.com/drive/folders/...')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if (empty($state) || ! GoogleDriveFolderScanner::isFolderUrl($state)) {
                            $set('selected_chapters', []);
                            return;
                        }
                        try {
                            $chapters = GoogleDriveFolderScanner::getChaptersList($state);
                            $set('selected_chapters', collect($chapters)->pluck('id')->toArray());
                        } catch (\Throwable) {
                            $set('selected_chapters', []);
                        }
                    })
                    ->helperText('Paste link and click outside to scan. General Access must be "Anyone with the link can view".'),

                CheckboxList::make('selected_chapters')
                    ->label('Detected Chapters (Auto-Arranged 1 to 19 in Numerical Order)')
                    ->bulkToggleable()
                    ->options(function (Get $get) {
                        $url = $get('folder_url');
                        if (empty($url) || ! GoogleDriveFolderScanner::isFolderUrl($url)) {
                            return [];
                        }
                        try {
                            $chapters = GoogleDriveFolderScanner::getChaptersList($url);
                            return collect($chapters)->mapWithKeys(fn ($c) => [$c['id'] => $c['display_name']])->toArray();
                        } catch (\Throwable $e) {
                            return ['error' => 'Could not read folder: ' . $e->getMessage()];
                        }
                    })
                    ->default(function (Get $get) {
                        $url = $get('folder_url');
                        if (empty($url) || ! GoogleDriveFolderScanner::isFolderUrl($url)) {
                            return [];
                        }
                        try {
                            $chapters = GoogleDriveFolderScanner::getChaptersList($url);
                            return collect($chapters)->pluck('id')->toArray();
                        } catch (\Throwable) {
                            return [];
                        }
                    })
                    ->visible(fn (Get $get) => filled($get('folder_url')))
                    ->helperText('All detected chapters are sorted 1 to 19 and selected by default. The algorithm automatically cleans names and orders materials (Notes first, Practice tests second, Interview questions third).'),

                TextInput::make('fallback_chapter_title')
                    ->label('Default Chapter Name (Optional)')
                    ->placeholder('Leave blank to use the Google Drive folder name')
                    ->helperText('Only used if the folder directly contains loose PDFs without subfolders.')
                    ->visible(fn (Get $get) => filled($get('folder_url'))),
            ])
            ->action(function (array $data, array $arguments) {
                if (empty($arguments['subject'])) {
                    throw new \Exception('No subject specified.');
                }
                $subject = $this->findSubject((int) $arguments['subject']);
                try {
                    $selectedChapters = ! empty($data['selected_chapters']) ? array_values(array_filter($data['selected_chapters'], fn ($id) => $id !== 'error')) : null;
                    $res = GoogleDriveFolderScanner::importIntoSubject(
                        $subject,
                        $data['folder_url'],
                        $this->outline(),
                        $selectedChapters,
                        filled($data['fallback_chapter_title'] ?? null) ? $data['fallback_chapter_title'] : null
                    );

                    Notification::make()
                        ->title("Curriculum Built: {$res['files_imported']} file(s) across {$res['chapters_created']} chapter(s)")
                        ->body("Auto-arranged and imported from “{$res['folder_title']}” into subject “{$subject->title}”.")
                        ->success()
                        ->persistent()
                        ->send();

                    if (! empty($res['errors'])) {
                        Notification::make()
                            ->title('Some files could not be imported')
                            ->body(implode("\n", array_slice($res['errors'], 0, 5)))
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Folder import failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /** Import a Google Drive folder as a brand new Subject in this course. */
    public function importCourseFolderAction(): Action
    {
        return Action::make('importCourseFolder')
            ->modalHeading('Import Google Drive Folder as New Subject')
            ->modalDescription('Paste a Google Drive folder link (e.g. "Chemistry Material"). The algorithm will create a new Subject, auto-arrange all 19 chapters in order, and download all PDFs automatically.')
            ->modalSubmitActionLabel('Import New Subject')
            ->modalWidth('2xl')
            ->schema([
                TextInput::make('folder_url')
                    ->label('Google Drive Folder Link')
                    ->placeholder('https://drive.google.com/drive/folders/...')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if (empty($state) || ! GoogleDriveFolderScanner::isFolderUrl($state)) {
                            $set('selected_chapters', []);
                            return;
                        }
                        try {
                            $chapters = GoogleDriveFolderScanner::getChaptersList($state);
                            $set('selected_chapters', collect($chapters)->pluck('id')->toArray());
                        } catch (\Throwable) {
                            $set('selected_chapters', []);
                        }
                    })
                    ->helperText('Paste link and click outside to scan. General Access must be "Anyone with the link can view".'),

                TextInput::make('subject_title')
                    ->label('Subject Name (Optional)')
                    ->placeholder('Leave empty to use the Google Drive folder name')
                    ->helperText('If left blank, the Subject will be named after the Google Drive folder.'),

                CheckboxList::make('selected_chapters')
                    ->label('Detected Chapters (Auto-Arranged 1 to 19 in Numerical Order)')
                    ->bulkToggleable()
                    ->options(function (Get $get) {
                        $url = $get('folder_url');
                        if (empty($url) || ! GoogleDriveFolderScanner::isFolderUrl($url)) {
                            return [];
                        }
                        try {
                            $chapters = GoogleDriveFolderScanner::getChaptersList($url);
                            return collect($chapters)->mapWithKeys(fn ($c) => [$c['id'] => $c['display_name']])->toArray();
                        } catch (\Throwable $e) {
                            return ['error' => 'Could not read folder: ' . $e->getMessage()];
                        }
                    })
                    ->default(function (Get $get) {
                        $url = $get('folder_url');
                        if (empty($url) || ! GoogleDriveFolderScanner::isFolderUrl($url)) {
                            return [];
                        }
                        try {
                            $chapters = GoogleDriveFolderScanner::getChaptersList($url);
                            return collect($chapters)->pluck('id')->toArray();
                        } catch (\Throwable) {
                            return [];
                        }
                    })
                    ->visible(fn (Get $get) => filled($get('folder_url')))
                    ->helperText('All detected chapters are sorted 1 to 19 and selected by default.'),
            ])
            ->action(function (array $data) {
                try {
                    $scanned = GoogleDriveFolderScanner::scanFolder($data['folder_url'], false);
                    $subjectTitle = filled($data['subject_title'] ?? null) ? trim($data['subject_title']) : $scanned['title'];

                    $subject = $this->outline()->addSubject($this->getRecord(), $subjectTitle);

                    $selectedChapters = ! empty($data['selected_chapters']) ? array_values(array_filter($data['selected_chapters'], fn ($id) => $id !== 'error')) : null;
                    $res = GoogleDriveFolderScanner::importIntoSubject(
                        $subject,
                        $data['folder_url'],
                        $this->outline(),
                        $selectedChapters
                    );

                    Notification::make()
                        ->title("Created Subject “{$subject->title}” with {$res['files_imported']} file(s)")
                        ->body("Auto-arranged {$res['chapters_created']} chapter(s) from “{$res['folder_title']}”.")
                        ->success()
                        ->persistent()
                        ->send();

                    if (! empty($res['errors'])) {
                        Notification::make()
                            ->title('Some files could not be imported')
                            ->body(implode("\n", array_slice($res['errors'], 0, 5)))
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Folder import failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /** Edit a subject's name/description. arguments: id */
    public function subjectAction(): Action
    {
        return Action::make('subject')->modalHeading('Edit subject')->modalWidth('lg')
            ->schema([
                TextInput::make('title')->label('Subject name')->required()->maxLength(150)->autofocus(),
                Textarea::make('description')->rows(3),
            ])
            ->fillForm(fn (array $arguments) => isset($arguments['id']) ? $this->findSubject((int) $arguments['id'])->only(['title', 'description']) : [])
            ->action(fn (array $data, array $arguments) => isset($arguments['id']) ? $this->outline()->updateSubject($this->findSubject((int) $arguments['id']), $data) : null);
    }

    /** Rename a chapter. arguments: id */
    public function chapterAction(): Action
    {
        return Action::make('chapter')->modalHeading('Rename chapter')->modalWidth('lg')
            ->schema([TextInput::make('title')->label('Chapter name')->required()->maxLength(150)->autofocus()])
            ->fillForm(fn (array $arguments) => isset($arguments['id']) ? ['title' => $this->findChapter((int) $arguments['id'])->title] : [])
            ->action(fn (array $data, array $arguments) => isset($arguments['id']) ? $this->outline()->renameChapter($this->findChapter((int) $arguments['id']), $data['title']) : null);
    }

    /**
     * Put a mock test on the course page (between subjects) or inside a subject (between chapters).
     * arguments: after (entry id to insert after), or subject (+ afterChapter) to place it between chapters.
     */
    public function mockTestAction(): Action
    {
        $courseId = $this->getRecord()->id;

        return Action::make('mockTest')
            ->modalHeading(fn (array $arguments) => isset($arguments['subject']) ? 'Add a mock test to “'.$this->findSubject($arguments['subject'])->title.'”' : 'Add a mock test to this course')
            ->modalWidth('2xl')->modalSubmitActionLabel(fn (array $arguments) => isset($arguments['subject']) ? 'Add here' : 'Add to course')
            ->schema(ContentForm::testPicker($courseId, TestKind::Mock))
            ->action(function (array $data, array $arguments) use ($courseId) {
                $test = ContentForm::resolveTest($data, $courseId, TestKind::Mock);
                $created = ($data['mode'] ?? 'existing') === 'new';
                $hint = $created ? ' Now add its questions with “Manage questions”.' : '';

                if (isset($arguments['subject'])) {
                    $this->outline()->addMockTestToSubject($this->findSubject($arguments['subject']), $test, $arguments['afterChapter'] ?? null);
                    Notification::make()->title('Mock test added between the chapters.'.$hint)->success()->send();

                    return;
                }

                $this->outline()->addMockTest($this->getRecord(), $test, $arguments['after'] ?? null);
                Notification::make()->title('Mock test added to the course.'.$hint)->success()->send();
            });
    }

    /** Move a content item to another chapter. arguments: id */
    public function moveContentAction(): Action
    {
        return Action::make('moveContent')->modalHeading('Move to another chapter')->modalWidth('lg')->modalSubmitActionLabel('Move')
            ->schema([
                Select::make('chapter_id')->label('Move to')->required()->searchable()
                    ->options(fn () => Chapter::whereIn('subject_id', $this->getRecord()->subjects()->pluck('id'))->with('subject')->get()
                        ->mapWithKeys(fn ($c) => [$c->id => "{$c->subject->title} → {$c->title}"])),
            ])
            ->action(fn (array $data, array $arguments) => $this->outline()->moveContent($this->findContent($arguments['id']), $this->findChapter($data['chapter_id'])));
    }

    // ------------------------------------------------------------------
    // Inline actions (wire:click in the Blade view)
    // ------------------------------------------------------------------

    public function addSubject(): void
    {
        $title = trim($this->newSubject);
        if ($title === '') {
            return;
        }
        $subject = $this->outline()->addSubject($this->getRecord(), $title);
        $this->newSubject = '';
        $this->dispatch('builder-focus', target: 'subject-'.$subject->id);
    }

    /** Adds a chapter and scrolls to it, so the admin can add its first content straight away. */
    public function addChapter(int $subjectId): void
    {
        $title = trim($this->newChapter[$subjectId] ?? '');
        if ($title === '') {
            return;
        }
        $chapter = $this->outline()->addChapter($this->findSubject($subjectId), $title);
        $this->newChapter[$subjectId] = '';
        $this->dispatch('builder-focus', target: 'chapter-'.$chapter->id, openMenu: true);
    }

    public function deleteContent(int $id): void
    {
        $this->outline()->deleteContent($this->findContent($id));
    }

    public function deleteChapter(int $id): void
    {
        $this->outline()->deleteChapter($this->findChapter($id));
    }

    /** Subjects are deleted with their chapters and contents; mock tests are only taken off the course page. */
    public function removeEntry(int $entryId): void
    {
        $this->outline()->removeEntry(CurriculumItem::where('course_id', $this->getRecord()->id)->findOrFail($entryId));
    }

    // ---- drag and drop ----

    public function reorderEntries(array $ids): void
    {
        $this->outline()->reorderEntries($this->getRecord(), array_map(fn ($id) => (int) str_replace('entry-', '', $id), $ids));
    }

    public function arrangeChapters(int $subjectId, array $ids): void
    {
        $this->outline()->arrangeChapters($this->findSubject($subjectId), array_map(fn ($id) => (int) str_replace('chapter-', '', $id), $ids));
    }

    public function arrangeContents(int $chapterId, array $ids): void
    {
        $this->outline()->arrangeContents($this->findChapter($chapterId), array_map(fn ($id) => (int) str_replace('content-', '', $id), $ids));
    }

    // ------------------------------------------------------------------

    private function outline(): CourseOutline
    {
        return app(CourseOutline::class);
    }

    // Lookups are always scoped to this course, so ids from the browser can't touch other courses.

    private function findSubject(int $id): Subject
    {
        return $this->getRecord()->subjects()->findOrFail($id);
    }

    private function findChapter(int $id): Chapter
    {
        return Chapter::whereIn('subject_id', $this->getRecord()->subjects()->pluck('id'))->findOrFail($id);
    }

    private function findContent(int $id): ChapterContent
    {
        return ChapterContent::whereIn('chapter_id', Chapter::whereIn('subject_id', $this->getRecord()->subjects()->pluck('id'))->pluck('id'))->findOrFail($id);
    }
}
