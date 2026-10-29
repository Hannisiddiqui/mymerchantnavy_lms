<?php
// usage: QUEUE_CONNECTION=sync php new_chapters.php [dry|apply]   (files are read from storage/app/private/incoming/)
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\ContentType;
use App\Enums\TestKind;
use App\Models\Chapter;
use App\Models\Subject;
use App\Models\Test;
use App\Services\Courses\CourseOutline;
use App\Services\Tests\QuestionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$apply = ($argv[1] ?? '') === 'apply';
$inc = 'incoming';
$disk = Storage::disk('local');
$subject = Subject::findOrFail(1);               // Maths, G.P Rating
abort_unless($subject->title === 'Maths', 500, 'subject 1 is not Maths');
$outline = app(CourseOutline::class);
$svc = app(QuestionService::class);

foreach (['prob', 'quad', 'ap'] as $k) {
    $j = json_decode(file_get_contents(__DIR__."/incoming_$k.json"), true);
    $title = $j['chapter'];
    if (Chapter::where('subject_id', $subject->id)->where('title', $title)->exists() || Test::where('title', "$title Practice Test")->exists()) {
        echo "SKIP $title: chapter or test already exists\n";
        continue;
    }
    foreach (['study', 'ladder'] as $f) {
        abort_unless($disk->exists("$inc/{$k}_$f.pdf"), 500, "missing $inc/{$k}_$f.pdf");
    }
    abort_unless(count($j['questions']) === 100, 500, "$title: not 100 questions");

    DB::beginTransaction();
    try {
        $chapter = $outline->addChapter($subject, $title);
        $pdfPaths = [];
        foreach (['study' => "$title Study Material", 'ladder' => "$title Practice Ladder"] as $f => $label) {
            if ($apply) {
                $dest = 'pdfs/'.Str::random(40).'.pdf';
                $disk->copy("$inc/{$k}_$f.pdf", $dest);
                $pdfPaths[] = $dest;
                $outline->saveContent($chapter, ContentType::Pdf, ['title' => $label, 'file' => $dest]);
            }
        }
        $test = Test::create(['course_id' => 1, 'title' => "$title Practice Test", 'kind' => TestKind::Practice,
            'duration_minutes' => 90, 'marks_correct' => 2, 'marks_wrong' => 0.25, 'is_published' => true]);
        foreach ($j['questions'] as $n => $form) {
            $q = $svc->save($form + ['subject_id' => $subject->id, 'chapter_id' => $chapter->id]);
            DB::table('test_questions')->insert(['test_id' => $test->id, 'question_id' => $q->id, 'position' => $n]);
        }
        $outline->saveContent($chapter, ContentType::PracticeTest, ['title' => "$title Practice Test", 'test_id' => $test->id]);
        echo ($apply ? 'CREATED' : 'DRY-OK')." chapter #{$chapter->id} (pos {$chapter->position}) $title | test #{$test->id} | questions ".DB::table('test_questions')->where('test_id', $test->id)->count()." | contents ".$chapter->contents()->count()."\n";
        $apply ? DB::commit() : DB::rollBack();
    } catch (Throwable $e) {
        DB::rollBack();
        foreach ($pdfPaths ?? [] as $p) { $disk->delete($p); }
        echo "FAILED $title: ".$e->getMessage()."\n";
        exit(1);
    }
}
