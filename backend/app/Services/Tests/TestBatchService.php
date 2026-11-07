<?php

namespace App\Services\Tests;

use App\Enums\ContentType;
use App\Enums\TestKind;
use App\Models\Chapter;
use App\Models\ChapterContent;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TestBatchService
{
    /**
     * Divide a Practice Test's questions into smaller batches/parts (e.g. 100 questions into 50 + 50).
     * Part 1 retains the current test ID and content record (renamed to e.g. "Quadratic Equation Practice Test 1").
     * Subsequent parts are created as new Tests and attached to the chapter right after Part 1.
     * Zero duplicate questions: every question is allocated to exactly one part.
     *
     * @return array{success: bool, total_questions: int, parts_created: int, base_title: string, parts: array<int, array{id: int, title: string, questions_count: int}>}
     *
     * @throws ValidationException
     */
    public function splitIntoParts(Test $test, int $questionsPerPart, ?Chapter $chapter = null): array
    {
        if ($questionsPerPart < 1) {
            throw ValidationException::withMessages([
                'batch_size' => 'Questions per batch must be at least 1.',
            ]);
        }

        $allQuestions = $test->questions()->orderByPivot('position')->pluck('questions.id')->all();
        $total = count($allQuestions);

        if ($total <= $questionsPerPart) {
            throw ValidationException::withMessages([
                'batch_size' => "This practice test only has {$total} questions. To divide it into parts, choose a batch size smaller than {$total}.",
            ]);
        }

        $chunks = array_chunk($allQuestions, $questionsPerPart);
        $numParts = count($chunks);

        if ($numParts < 2) {
            throw ValidationException::withMessages([
                'batch_size' => "Batch size {$questionsPerPart} would not divide {$total} questions into multiple parts.",
            ]);
        }

        // Clean base title (e.g. "Quadratic Equation Practice Test 1" -> "Quadratic Equation Practice Test")
        $baseTitle = preg_replace('/\s*[-–(]?\s*part\s*\d+\s*(?:of\s*\d+)?\)?$/i', '', $test->title);
        $baseTitle = preg_replace('/\s+\d+$/', '', $baseTitle);
        $baseTitle = trim($baseTitle);

        // Find associated chapter content item
        $content = ChapterContent::where('contentable_type', $test->getMorphClass())
            ->where('contentable_id', $test->id)
            ->first();

        if (! $chapter && $content) {
            $chapter = $content->chapter;
        }

        $partsInfo = [];

        DB::transaction(function () use ($test, $chunks, $numParts, $baseTitle, $total, $content, $chapter, &$partsInfo) {
            // 1. Configure Part 1 (existing test)
            $part1Title = "{$baseTitle} 1";
            $test->update(['title' => $part1Title]);

            if ($content) {
                $content->update(['title' => $part1Title]);
            }

            // Detach questions not in chunk 0
            $chunk0 = $chunks[0];
            $chunk0Map = array_fill_keys($chunk0, true);
            $toDetach = array_filter($test->questions()->pluck('questions.id')->all(), fn ($qId) => ! isset($chunk0Map[$qId]));

            if (! empty($toDetach)) {
                $test->questions()->detach($toDetach);
            }

            // Re-normalize positions for Part 1
            foreach ($chunk0 as $pos => $qId) {
                DB::table('test_questions')
                    ->where('test_id', $test->id)
                    ->where('question_id', $qId)
                    ->update(['position' => $pos]);
            }

            $partsInfo[] = [
                'id'              => $test->id,
                'title'           => $part1Title,
                'questions_count' => count($chunk0),
            ];

            // 2. Create and attach Parts 2..N
            $currentPos = $content ? $content->position : 0;

            for ($i = 1; $i < $numParts; $i++) {
                $partNumber = $i + 1;
                $partTitle = "{$baseTitle} {$partNumber}";
                $chunk = $chunks[$i];

                $duration = (int) max(15, round(($test->duration_minutes / max($total, 1)) * count($chunk)));

                $newTest = Test::create([
                    'course_id'        => $test->course_id,
                    'kind'             => TestKind::Practice,
                    'title'            => $partTitle,
                    'duration_minutes' => $duration,
                    'marks_correct'    => $test->marks_correct,
                    'marks_wrong'      => $test->marks_wrong,
                    'is_published'     => $test->is_published,
                ]);

                // Attach questions to new test in order
                $attachData = [];
                foreach ($chunk as $pos => $qId) {
                    $attachData[$qId] = ['position' => $pos];
                }
                $newTest->questions()->attach($attachData);

                // Add to chapter as ChapterContent right after previous part
                if ($chapter) {
                    $currentPos++;
                    ChapterContent::create([
                        'chapter_id'       => $chapter->id,
                        'type'             => ContentType::PracticeTest,
                        'title'            => $partTitle,
                        'contentable_type' => $newTest->getMorphClass(),
                        'contentable_id'   => $newTest->id,
                        'position'         => $currentPos,
                    ]);
                }

                $partsInfo[] = [
                    'id'              => $newTest->id,
                    'title'           => $partTitle,
                    'questions_count' => count($chunk),
                ];
            }

            // Re-order positions of any subsequent content items in chapter
            if ($chapter && $content) {
                $subsequentContents = $chapter->contents()
                    ->where('position', '>', $content->position)
                    ->whereNotIn('contentable_id', collect($partsInfo)->pluck('id')->all())
                    ->orderBy('position')
                    ->get();

                foreach ($subsequentContents as $sub) {
                    $currentPos++;
                    $sub->update(['position' => $currentPos]);
                }
            }
        });

        return [
            'success'         => true,
            'total_questions' => $total,
            'parts_created'   => $numParts,
            'base_title'      => $baseTitle,
            'parts'           => $partsInfo,
        ];
    }

    /**
     * Merge all questions from a source Practice Test into a target Practice Test.
     * Prevents duplicate questions: only unique questions not already in target are added.
     * The source test's chapter content is removed, and the source test record is cleaned up.
     *
     * @return array{success: bool, target_title: string, added_count: int, total_questions: int, skipped_duplicates: int}
     *
     * @throws ValidationException
     */
    public function mergeTests(Test $targetTest, Test $sourceTest, ?Chapter $chapter = null): array
    {
        if ($targetTest->id === $sourceTest->id) {
            throw ValidationException::withMessages([
                'merge_source_test_id' => 'Cannot merge a practice test into itself.',
            ]);
        }

        $targetQuestionIds = $targetTest->questions()->pluck('questions.id')->all();
        $sourceQuestionIds = $sourceTest->questions()->orderByPivot('position')->pluck('questions.id')->all();

        $toAdd = array_values(array_diff($sourceQuestionIds, $targetQuestionIds));
        $skipped = count($sourceQuestionIds) - count($toAdd);

        $addedCount = count($toAdd);

        DB::transaction(function () use ($targetTest, $sourceTest, $toAdd) {
            // 1. Append non-duplicate questions from source to target
            if (! empty($toAdd)) {
                $startPos = (DB::table('test_questions')->where('test_id', $targetTest->id)->max('position') ?? -1) + 1;
                $attachData = [];
                foreach ($toAdd as $idx => $qId) {
                    $attachData[$qId] = ['position' => $startPos + $idx];
                }
                $targetTest->questions()->attach($attachData);
            }

            // 2. Remove source test from ChapterContent
            ChapterContent::where('contentable_type', $sourceTest->getMorphClass())
                ->where('contentable_id', $sourceTest->id)
                ->delete();

            // 3. Clean up the target test title if it had "Part 1" or " 1"
            $cleanTitle = preg_replace('/\s*[-–(]?\s*part\s*1\s*(?:of\s*\d+)?\)?$/i', '', $targetTest->title);
            $cleanTitle = preg_replace('/\s+1$/', '', $cleanTitle);
            $cleanTitle = trim($cleanTitle);

            if (! empty($cleanTitle)) {
                $targetTest->update(['title' => $cleanTitle]);

                // Update ChapterContent title
                ChapterContent::where('contentable_type', $targetTest->getMorphClass())
                    ->where('contentable_id', $targetTest->id)
                    ->update(['title' => $cleanTitle]);
            }

            // 4. Safely delete the source test record (questions are now in targetTest, so Question::delete() will not delete them)
            $sourceTest->delete();
        });

        return [
            'success'            => true,
            'target_title'       => $targetTest->fresh()->title,
            'added_count'        => $addedCount,
            'total_questions'    => $targetTest->fresh()->questions()->count(),
            'skipped_duplicates' => $skipped,
        ];
    }

    /**
     * Get other practice tests in the same course eligible for merging into the given test.
     *
     * @return array<int, string> [test_id => "Test Title (X questions)"]
     */
    public function getMergeableTests(Test $currentTest): array
    {
        return Test::where('course_id', $currentTest->course_id)
            ->where('kind', TestKind::Practice)
            ->where('id', '!=', $currentTest->id)
            ->withCount('questions')
            ->orderBy('title')
            ->get()
            ->mapWithKeys(fn (Test $t) => [$t->id => "{$t->title} ({$t->questions_count} questions)"])
            ->toArray();
    }
}
