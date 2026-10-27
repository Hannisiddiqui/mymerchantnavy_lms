<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveNoteRequest;
use App\Models\ChapterContent;
use App\Models\StudentNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A student's own notes on what they are reading or watching: add, edit and delete notes on a PDF, a written note or a
 * video; for a PDF each note can be pinned to a page. Notes are private: every query is limited to the logged-in student.
 */
class NoteController extends Controller
{
    /** Content types a note can be written on (a test or an assignment has its own flow). */
    private const NOTEABLE = [ContentType::Pdf, ContentType::Note, ContentType::Video, ContentType::LiveClass];

    /** GET /api/v1/contents/{id}/notes — the student's notes on this item: whole-item notes first, then by page. */
    public function forContent(Request $request, int $id): JsonResponse
    {
        $content = $this->noteable($request, $id);
        $notes = StudentNote::where('user_id', $request->user()->id)->where('chapter_content_id', $content->id)
            ->orderByRaw('page is not null')->orderBy('page')->orderBy('id')->get();

        return $this->data($notes->map(fn (StudentNote $n) => $this->shape($n))->values()->all());
    }

    /** POST /api/v1/contents/{id}/notes */
    public function store(SaveNoteRequest $request, int $id): JsonResponse
    {
        $content = $this->noteable($request, $id);
        $note = StudentNote::create([
            'user_id' => $request->user()->id,
            'chapter_content_id' => $content->id,
            'page' => $this->pageFor($content, $request->validated('page')),
            'body' => $request->validated('body'),
        ]);

        return $this->data($this->shape($note), 201);
    }

    /** PUT /api/v1/notes/{id} */
    public function update(SaveNoteRequest $request, int $id): JsonResponse
    {
        $note = $this->own($request, $id);
        $note->update([
            'body' => $request->validated('body'),
            // an edit may move the note to another page, or take it off its page
            'page' => $request->has('page') ? $this->pageFor($note->content, $request->validated('page')) : $note->page,
        ]);

        return $this->data($this->shape($note->refresh()));
    }

    /** DELETE /api/v1/notes/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->own($request, $id)->delete();

        return $this->data(null);
    }

    /**
     * GET /api/v1/notes — every note of the student, newest first, each with where it belongs (course, subject, chapter,
     * item), for the "My Notes" page. Notes on content the student can no longer open are left out.
     */
    public function index(Request $request): JsonResponse
    {
        $catalog = $this->catalog($request);
        $courseIds = $catalog->courseIds();

        $notes = StudentNote::where('user_id', $request->user()->id)
            ->with('content.chapter.subject.course')->latest('updated_at')->latest('id')->get()
            ->filter(fn (StudentNote $n) => $n->content?->chapter?->subject && $courseIds->contains($n->content->chapter->subject->course_id))
            ->map(function (StudentNote $n) {
                $chapter = $n->content->chapter;
                $subject = $chapter->subject;

                return $this->shape($n) + [
                    'contentTitle' => $n->content->title,
                    'contentType' => $n->content->type->value,
                    'chapterTitle' => $chapter->title,
                    'subjectId' => $subject->id,
                    'subjectTitle' => $subject->title,
                    'courseId' => $subject->course_id,
                    'courseTitle' => $subject->course->title,
                ];
            })->values()->all();

        return $this->data($notes);
    }

    // ------------------------------------------------------------------

    /** The content item, if the student may open it and a note can be written on it (otherwise "not found"). */
    private function noteable(Request $request, int $id): ChapterContent
    {
        $content = $this->catalog($request)->findContent($id);
        abort_unless(in_array($content->type, self::NOTEABLE, true), 404);

        return $content;
    }

    /** The student's own note (another student's note is "not found"), on content they can still open. */
    private function own(Request $request, int $id): StudentNote
    {
        $note = StudentNote::where('user_id', $request->user()->id)->findOrFail($id);
        $this->catalog($request)->findContent($note->chapter_content_id);

        return $note;
    }

    /** Only a PDF has pages; the page must exist in it. */
    private function pageFor(ChapterContent $content, mixed $page): ?int
    {
        if ($page === null || $content->type !== ContentType::Pdf) {
            return null;
        }
        $count = (int) ($content->contentable->page_count ?? 0);

        return $count > 0 ? min((int) $page, $count) : (int) $page;
    }

    /** Matches `StudentNote` in student-app/src/types/index.ts. */
    private function shape(StudentNote $note): array
    {
        return [
            'id' => $note->id,
            'contentId' => $note->chapter_content_id,
            'page' => $note->page,
            'body' => $note->body,
            'createdAt' => $note->created_at->toIso8601String(),
            'updatedAt' => $note->updated_at->toIso8601String(),
        ];
    }
}
