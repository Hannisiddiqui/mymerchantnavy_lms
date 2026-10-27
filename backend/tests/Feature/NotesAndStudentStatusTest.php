<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Filament\Resources\StudentResource\Pages\EditStudent;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Models\Admin;
use App\Models\Chapter;
use App\Models\ChapterContent;
use App\Models\Course;
use App\Models\StudentNote;
use App\Models\Test;
use App\Models\User;
use App\Services\Courses\CourseOutline;
use App\Services\Courses\EnrollmentService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Notes on what a student reads, students that can only be active or inactive, and "read" that is marked by hand. */
class NotesAndStudentStatusTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Chapter $chapter;

    private ChapterContent $pdf;

    private ChapterContent $written;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $outline = app(CourseOutline::class);
        $this->course = Course::create(['title' => 'GP Rating', 'slug' => 'gp']);
        $subject = $outline->addSubject($this->course, 'Maths');
        $this->chapter = $outline->addChapter($subject, 'Percentage');
        $this->pdf = $outline->saveContent($this->chapter, ContentType::Pdf, ['title' => 'Percentage guide', 'file' => 'pdfs/percentage.pdf']);
        $this->pdf->contentable->update(['page_count' => 12, 'processed_at' => now()]);
        $this->written = $outline->saveContent($this->chapter, ContentType::Note, ['title' => 'Quick notes', 'body' => '<p>one two three</p>']);
    }

    private function student(bool $enrolled = true): User
    {
        $user = User::factory()->create();
        if ($enrolled) {
            app(EnrollmentService::class)->assign($user, $this->course);
        }

        return $user;
    }

    // ------------------------------------------------------------------ notes

    public function test_a_student_adds_edits_and_deletes_notes_on_a_pdf(): void
    {
        $this->actingAs($this->student(), 'sanctum');
        $url = "/api/v1/contents/{$this->pdf->id}/notes";

        $this->getJson($url)->assertOk()->assertExactJson(['data' => []]);

        $first = $this->postJson($url, ['body' => '  Discount is on the marked price  ', 'page' => 7])->assertCreated()->json('data');
        $this->assertSame('Discount is on the marked price', $first['body'], 'the text is trimmed');
        $this->assertSame(7, $first['page']);
        $this->postJson($url, ['body' => 'Whole guide: revise before the test'])->assertCreated();
        $this->postJson($url, ['body' => 'Formula for successive discounts', 'page' => 3])->assertCreated();

        // whole-guide notes first, then by page
        $notes = $this->getJson($url)->assertOk()->json('data');
        $this->assertSame([null, 3, 7], array_column($notes, 'page'));

        // edit the text and move it to another page
        $this->putJson("/api/v1/notes/{$first['id']}", ['body' => 'Discount is on the MARKED price', 'page' => 8])->assertOk()
            ->assertJsonPath('data.body', 'Discount is on the MARKED price')->assertJsonPath('data.page', 8);
        // edit without a page keeps it where it was
        $this->putJson("/api/v1/notes/{$first['id']}", ['body' => 'Marked price, not cost price'])->assertOk()->assertJsonPath('data.page', 8);
        // take it off its page
        $this->putJson("/api/v1/notes/{$first['id']}", ['body' => 'Marked price, not cost price', 'page' => null])->assertOk()->assertJsonPath('data.page', null);

        $this->deleteJson("/api/v1/notes/{$first['id']}")->assertOk();
        $this->assertCount(2, $this->getJson($url)->json('data'));
    }

    public function test_notes_are_private_to_the_student_who_wrote_them(): void
    {
        $owner = $this->student();
        $other = $this->student();
        $noteId = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/contents/{$this->pdf->id}/notes", ['body' => 'mine', 'page' => 2])->json('data.id');

        $this->actingAs($other, 'sanctum');
        $this->getJson("/api/v1/contents/{$this->pdf->id}/notes")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/notes')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/notes/{$noteId}", ['body' => 'hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/notes/{$noteId}")->assertNotFound();

        $this->assertSame('mine', StudentNote::findOrFail($noteId)->body);
    }

    public function test_notes_need_access_to_the_content_and_a_readable_content_type(): void
    {
        $outline = app(CourseOutline::class);
        $assignment = $outline->saveContent($this->chapter, ContentType::Assignment, ['title' => 'Homework', 'instructions' => 'Solve']);
        $test = Test::create(['course_id' => $this->course->id, 'title' => 'Practice', 'kind' => 'practice', 'duration_minutes' => 10, 'is_published' => true]);
        $practice = $outline->saveContent($this->chapter, ContentType::PracticeTest, ['title' => 'Practice', 'test_id' => $test->id]);

        $this->actingAs($this->student(false), 'sanctum');
        $this->getJson("/api/v1/contents/{$this->pdf->id}/notes")->assertNotFound();
        $this->postJson("/api/v1/contents/{$this->pdf->id}/notes", ['body' => 'x'])->assertNotFound();

        $this->actingAs($this->student(), 'sanctum');
        foreach ([$assignment, $practice] as $content) {
            $this->postJson("/api/v1/contents/{$content->id}/notes", ['body' => 'x'])->assertNotFound();
        }
        $this->postJson("/api/v1/contents/{$this->written->id}/notes", ['body' => 'on a written note'])->assertCreated();
    }

    public function test_note_rules(): void
    {
        $this->actingAs($this->student(), 'sanctum');
        $url = "/api/v1/contents/{$this->pdf->id}/notes";

        $this->postJson($url, ['body' => '   '])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson($url, [])->assertUnprocessable();
        $this->postJson($url, ['body' => str_repeat('a', 5001)])->assertUnprocessable();
        $this->postJson($url, ['body' => str_repeat('a', 5000)])->assertCreated();
        $this->postJson($url, ['body' => 'x', 'page' => 0])->assertUnprocessable();

        // a page past the end of the PDF is pinned to its last page; a written note has no pages at all
        $this->postJson($url, ['body' => 'too far', 'page' => 99])->assertCreated()->assertJsonPath('data.page', 12);
        $this->postJson("/api/v1/contents/{$this->written->id}/notes", ['body' => 'no pages here', 'page' => 4])->assertCreated()->assertJsonPath('data.page', null);
    }

    public function test_my_notes_lists_everything_with_where_it_belongs(): void
    {
        $student = $this->student();
        $this->actingAs($student, 'sanctum');
        $this->postJson("/api/v1/contents/{$this->pdf->id}/notes", ['body' => 'on the pdf', 'page' => 5])->assertCreated();
        $this->postJson("/api/v1/contents/{$this->written->id}/notes", ['body' => 'on the note'])->assertCreated();

        $all = $this->getJson('/api/v1/notes')->assertOk()->json('data');
        $this->assertCount(2, $all);
        $pdfNote = collect($all)->firstWhere('contentId', $this->pdf->id);
        $this->assertSame(['Percentage guide', 'pdf', 'Percentage', 'Maths', 'GP Rating', 5], [
            $pdfNote['contentTitle'], $pdfNote['contentType'], $pdfNote['chapterTitle'], $pdfNote['subjectTitle'], $pdfNote['courseTitle'], $pdfNote['page'],
        ]);

        // losing the course hides its notes (they come back if the course is assigned again)
        app(EnrollmentService::class)->remove($student, $this->course);
        $this->getJson('/api/v1/notes')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(2, StudentNote::where('user_id', $student->id)->count());
    }

    // ------------------------------------------------------------------ read status

    public function test_reading_a_document_never_marks_it_read_only_the_button_does(): void
    {
        $student = $this->student();
        $this->actingAs($student, 'sanctum');

        // open it, fetch every page, take notes: still not read
        $this->getJson("/api/v1/contents/{$this->pdf->id}")->assertOk()->assertJsonPath('data.content.completed', false);
        $this->getJson("/api/v1/contents/{$this->pdf->id}/pages")->assertOk();
        $this->postJson("/api/v1/contents/{$this->pdf->id}/notes", ['body' => 'a note', 'page' => 12])->assertCreated();
        $this->assertFalse($this->getJson("/api/v1/contents/{$this->pdf->id}")->json('data.content.completed'));

        $this->postJson("/api/v1/contents/{$this->pdf->id}/complete")->assertOk();
        $this->assertTrue($this->getJson("/api/v1/contents/{$this->pdf->id}")->json('data.content.completed'));
    }

    // ------------------------------------------------------------------ students: active or inactive only

    public function test_a_student_can_never_be_deleted(): void
    {
        $student = $this->student();

        $this->expectException(\LogicException::class);
        $student->delete();
    }

    public function test_the_admin_can_mark_a_student_inactive_and_active_but_has_no_delete(): void
    {
        $this->actingAs(Admin::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password']), 'admin');
        Filament::setCurrentPanel('admin');
        $student = $this->student();
        $student->createToken('phone');
        $this->assertCount(1, $student->tokens);

        // the edit page has no delete button
        $html = $this->get("/admin/students/{$student->id}/edit")->assertOk()->getContent();
        $this->assertStringNotContainsString('Delete', $html);
        Livewire::test(EditStudent::class, ['record' => $student->id])->assertActionDoesNotExist('delete');

        // the list: mark inactive logs them out everywhere, mark active lets them back in
        Livewire::test(ListStudents::class)
            ->assertTableActionVisible('deactivate', $student)->assertTableActionHidden('activate', $student)
            ->callTableAction('deactivate', $student)
            ->assertTableActionVisible('activate', $student)->assertTableActionHidden('deactivate', $student);
        $this->assertFalse($student->fresh()->is_active);
        $this->assertCount(0, $student->fresh()->tokens, 'an inactive student is logged out of every device');

        Livewire::test(ListStudents::class)->callTableAction('activate', $student);
        $this->assertTrue($student->fresh()->is_active);

        // no delete anywhere in the student list
        Livewire::test(ListStudents::class)->assertTableActionDoesNotExist('delete')->assertTableBulkActionDoesNotExist('delete');
    }

    public function test_switching_the_account_off_on_the_edit_form_also_logs_the_student_out(): void
    {
        $this->actingAs(Admin::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password']), 'admin');
        Filament::setCurrentPanel('admin');
        $student = $this->student();
        $student->createToken('laptop');

        Livewire::test(EditStudent::class, ['record' => $student->id])->fillForm(['is_active' => false])->call('save')->assertHasNoFormErrors();

        $this->assertFalse($student->fresh()->is_active);
        $this->assertCount(0, $student->fresh()->tokens);
    }
}
