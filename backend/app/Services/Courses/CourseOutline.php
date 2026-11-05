<?php

namespace App\Services\Courses;

use App\Enums\ContentType;
use App\Enums\TestKind;
use App\Jobs\ProcessPdfDocument;
use App\Models\Assignment;
use App\Models\Chapter;
use App\Models\ChapterContent;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\LiveClass;
use App\Models\Note;
use App\Models\PdfDocument;
use App\Models\Subject;
use App\Models\Test;
use App\Models\Video;
use App\Services\Courses\RemotePdfDownloader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything the course builder (admin) does to a course's structure:
 *
 *   Course ── entries (ordered) ──┬─ Subject ── Chapters (ordered) ── Contents (ordered)
 *                                 └─ Mock test
 *
 * Each method changes one thing and keeps `position` columns tidy, so the admin UI stays simple.
 * Rows are updated in place (never recreated), so student progress survives edits and reordering.
 */
class CourseOutline
{
    // ---------- reading ----------

    /** Ordered entries (subjects and mock tests) with everything needed to draw the builder. */
    public function entries(Course $course): Collection
    {
        return $course->curriculum()
            ->with(['item' => fn (MorphTo $morph) => $morph
                ->morphWith([Subject::class => ['chapters.contents.contentable', 'chapters.test' => fn ($q) => $q->withCount('questions')]])
                ->morphWithCount([Test::class => ['questions']])])
            ->get()
            ->filter(fn (CurriculumItem $entry) => $entry->item !== null)
            ->values();
    }

    public function stats(Collection $entries): array
    {
        $subjects = $entries->pluck('item')->filter(fn ($i) => $i instanceof Subject);
        $allChapters = $subjects->flatMap->chapters;
        $chapters = $allChapters->reject->isMockTest();

        return [
            'subjects' => $subjects->count(),
            'chapters' => $chapters->count(),
            'contents' => $chapters->flatMap->contents->count(),
            'mockTests' => $entries->pluck('item')->filter(fn ($i) => $i instanceof Test)->count() + $allChapters->filter->isMockTest()->count(),
            'emptyChapters' => $chapters->filter(fn ($c) => $c->contents->isEmpty())->count(),
        ];
    }

    /** One-line summary under a content title, e.g. "38 min · YouTube". */
    public function describe(ChapterContent $content): string
    {
        $m = $content->contentable;

        return match ($content->type) {
            ContentType::Video => collect([$m?->duration_seconds ? round($m->duration_seconds / 60).' min' : null, ucfirst($m?->provider->value ?? '')])->filter()->implode(' · '),
            ContentType::LiveClass => $m ? $m->starts_at->format('d M Y, h:i A').' · '.$m->duration_minutes.' min' : '',
            ContentType::Pdf => $m ? basename($m->file_path) : '',
            ContentType::Note => $m ? Str::words(strip_tags($m->body), 8) : '',
            ContentType::Assignment => $m?->due_at ? 'Due '.$m->due_at->format('d M Y') : 'No due date',
            ContentType::PracticeTest => $m ? $m->questions()->count().' questions · '.$m->duration_minutes.' min'.($m->is_published ? '' : ' · draft') : '',
        };
    }

    /** Values for the edit form of one content item. */
    public function contentData(ChapterContent $content): array
    {
        $m = $content->contentable;
        $data = ['title' => $content->title];

        return $data + match ($content->type) {
            ContentType::Video => ['provider' => $m->provider->value, 'provider_ref' => $m->provider_ref, 'duration_minutes' => (int) round($m->duration_seconds / 60)],
            ContentType::LiveClass => ['provider' => $m->provider, 'starts_at' => $m->starts_at, 'duration_minutes' => $m->duration_minutes],
            ContentType::Pdf => ['source_type' => 'upload', 'file' => $m->file_path, 'pdf_url' => ''],
            ContentType::Note => ['body' => $m->body],
            ContentType::Assignment => ['instructions' => $m->instructions, 'due_at' => $m->due_at],
            ContentType::PracticeTest => ['mode' => 'existing', 'test_id' => $m->id],
        };
    }

    // ---------- subjects & mock tests (course level) ----------

    public function addSubject(Course $course, string $title, ?string $description = null, ?int $afterEntryId = null): Subject
    {
        $subject = $course->subjects()->create(['title' => $title, 'description' => $description]);
        $this->placeEntry($course, $subject, $afterEntryId);

        return $subject;
    }

    public function updateSubject(Subject $subject, array $data): void
    {
        $subject->update(['title' => $data['title'], 'description' => $data['description'] ?? null]);
    }

    public function addMockTest(Course $course, Test $test, ?int $afterEntryId = null): void
    {
        $this->placeEntry($course, $test, $afterEntryId);
    }

    /** Takes the mock test out of the course page (the test itself and its attempts are kept). */
    public function removeEntry(CurriculumItem $entry): void
    {
        $item = $entry->item;
        DB::transaction(function () use ($entry, $item) {
            $entry->delete();
            if ($item instanceof Subject) {
                $this->deleteSubject($item);
            }
        });
    }

    /** @param  array<int,string>  $entryIds  CurriculumItem ids in their new order */
    public function reorderEntries(Course $course, array $entryIds): void
    {
        foreach (array_values($entryIds) as $position => $id) {
            CurriculumItem::where('course_id', $course->id)->whereKey($id)->update(['position' => $position]);
        }
    }

    // ---------- chapters ----------

    public function addChapter(Subject $subject, string $title): Chapter
    {
        return $subject->chapters()->create([
            'title' => $title,
            'position' => ($subject->chapters()->max('position') ?? -1) + 1,
        ]);
    }

    /** Puts a mock test in a subject's chapter list: at the end, or right after chapter `$afterChapterId`. */
    public function addMockTestToSubject(Subject $subject, Test $test, ?int $afterChapterId = null): Chapter
    {
        return DB::transaction(function () use ($subject, $test, $afterChapterId) {
            $entry = $subject->chapters()->create([
                'title' => $test->title,
                'test_id' => $test->id,
                'position' => ($subject->chapters()->max('position') ?? -1) + 1,
            ]);

            if ($afterChapterId) {
                $order = $subject->chapters()->pluck('id')->reject(fn ($id) => $id === $entry->id)->values();
                $at = $order->search($afterChapterId);
                $order->splice($at === false ? $order->count() : $at + 1, 0, [$entry->id]);
                $this->arrangeChapters($subject, $order->all());
            }

            return $entry;
        });
    }

    /** Mock tests of the course that are not on the course page or inside a subject yet. */
    public function unplacedMockTests(Course $course): Collection
    {
        $placed = CurriculumItem::where('course_id', $course->id)->where('item_type', (new Test)->getMorphClass())->pluck('item_id')
            ->merge(Chapter::whereIn('subject_id', $course->subjects()->pluck('id'))->whereNotNull('test_id')->pluck('test_id'));

        return Test::where('course_id', $course->id)->where('kind', TestKind::Mock)->whereNotIn('id', $placed)->pluck('title', 'id');
    }

    public function renameChapter(Chapter $chapter, string $title): void
    {
        $chapter->update(['title' => $title]);
    }

    public function deleteChapter(Chapter $chapter): void
    {
        DB::transaction(function () use ($chapter) {
            $this->deleteContents($chapter->contents()->with('contentable')->get());
            $chapter->delete();
        });
    }

    /**
     * Drag-and-drop of chapters. Handles moving a chapter to another subject too.
     *
     * @param  array<int,string>  $orderedIds  chapter ids now inside `$subject`
     */
    public function arrangeChapters(Subject $subject, array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $position => $id) {
            Chapter::whereKey($id)->whereIn('subject_id', $subject->course->subjects()->pluck('id'))
                ->update(['subject_id' => $subject->id, 'position' => $position]);
        }
    }

    // ---------- contents ----------

    /** Creates a content item, or updates `$existing`. */
    public function saveContent(Chapter $chapter, ContentType $type, array $data, ?ChapterContent $existing = null): ChapterContent
    {
        return DB::transaction(function () use ($chapter, $type, $data, $existing) {
            $material = $this->saveMaterial($type, $existing?->contentable, $data);

            $content = $existing ?? new ChapterContent([
                'chapter_id' => $chapter->id,
                'position' => ($chapter->contents()->max('position') ?? -1) + 1,
            ]);
            $content->fill([
                'type' => $type,
                'title' => $data['title'],
                'contentable_type' => $material->getMorphClass(),
                'contentable_id' => $material->getKey(),
            ])->save();

            return $content;
        });
    }

    public function deleteContent(ChapterContent $content): void
    {
        DB::transaction(fn () => $this->deleteContents(collect([$content])));
    }

    public function moveContent(ChapterContent $content, Chapter $to): void
    {
        $content->update(['chapter_id' => $to->id, 'position' => ($to->contents()->max('position') ?? -1) + 1]);
    }

    /**
     * Drag-and-drop of contents, including dragging between chapters.
     * `$ids` are the content ids now inside `$chapter`, in order.
     *
     * @param  array<int,string>  $ids
     */
    public function arrangeContents(Chapter $chapter, array $ids): void
    {
        $courseChapters = Chapter::whereIn('subject_id', $chapter->subject->course->subjects()->pluck('id'))->pluck('id');

        foreach (array_values($ids) as $position => $id) {
            ChapterContent::whereKey($id)->whereIn('chapter_id', $courseChapters)
                ->update(['chapter_id' => $chapter->id, 'position' => $position]);
        }
    }

    // ---------- internals ----------

    /** Appends an entry to the course page, or inserts it right after `$afterEntryId`. */
    private function placeEntry(Course $course, Model $item, ?int $afterEntryId): void
    {
        $entry = CurriculumItem::create([
            'course_id' => $course->id,
            'item_type' => $item->getMorphClass(),
            'item_id' => $item->getKey(),
            'position' => ($course->curriculum()->max('position') ?? -1) + 1,
        ]);

        if ($afterEntryId) {
            $order = $course->curriculum()->pluck('id')->reject(fn ($id) => $id === $entry->id)->values();
            $at = $order->search($afterEntryId);
            $order->splice($at === false ? $order->count() : $at + 1, 0, [$entry->id]);
            $this->reorderEntries($course, $order->all());
        }
    }

    /** Creates/updates the record that holds the actual material (video, PDF, note…). */
    private function saveMaterial(ContentType $type, ?Model $existing, array $data): Model
    {
        $fill = function (string $model, array $values) use ($existing): Model {
            $record = $existing instanceof $model ? $existing : new $model;
            $record->fill($values)->save();

            return $record;
        };

        return match ($type) {
            ContentType::Video => $fill(Video::class, [
                'provider' => $data['provider'],
                'provider_ref' => $data['provider_ref'],
                'duration_seconds' => (int) ($data['duration_minutes'] ?? 0) * 60,
            ]),
            ContentType::LiveClass => $fill(LiveClass::class, [
                'provider' => $data['provider'] ?? null,
                'starts_at' => $data['starts_at'],
                'duration_minutes' => $data['duration_minutes'] ?? 60,
            ]),
            ContentType::Pdf => $this->savePdf($existing, $fill, $data),
            ContentType::Note => $fill(Note::class, ['body' => $data['body']]),
            ContentType::Assignment => $fill(Assignment::class, [
                'instructions' => $data['instructions'],
                'due_at' => $data['due_at'] ?? null,
            ]),
            ContentType::PracticeTest => Test::findOrFail($data['test_id']),
        };
    }

    /** A new or replaced file has to be rendered to page images again (in the background). */
    private function savePdf(?Model $existing, \Closure $fill, array|string $data): Model
    {
        $file = null;
        if (is_array($data)) {
            $sourceType = $data['source_type'] ?? null;
            $url = $data['pdf_url'] ?? null;
            if ($sourceType === 'url' || (! empty($url) && empty($data['file']))) {
                $downloaded = RemotePdfDownloader::download($url);
                $file = $downloaded['file_path'];
            } else {
                $file = $data['file'] ?? null;
            }
        } else {
            $file = $data;
        }

        if (is_array($file)) {
            $file = reset($file);
        }

        if (empty($file)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pdf_url' => 'Please provide a valid Google Drive link or upload a PDF file.',
                'file' => 'Please provide a valid Google Drive link or upload a PDF file.',
            ]);
        }

        $unchanged = $existing instanceof PdfDocument && $existing->file_path === $file && $existing->processed_at;

        $pdf = $fill(PdfDocument::class, [
            'file_path' => $file,
            'processed_at' => $unchanged ? $existing->processed_at : null,
        ]);

        if (! $unchanged) {
            ProcessPdfDocument::dispatch($pdf->id)->afterCommit();
        }

        return $pdf;
    }

    private function deleteSubject(Subject $subject): void
    {
        $subject->chapters()->with('contents.contentable')->get()
            ->each(fn (Chapter $chapter) => $this->deleteContents($chapter->contents));
        $subject->delete();
    }

    /** Deletes content rows and the material they own (tests are shared with the Tests page, so they stay). */
    private function deleteContents(Collection $contents): void
    {
        foreach ($contents as $content) {
            if (! $content->contentable instanceof Test) {
                $content->contentable?->delete();
            }
            $content->delete();
        }
    }
}
