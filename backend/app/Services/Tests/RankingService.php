<?php

namespace App\Services\Tests;

use App\Models\TestAttempt;

/**
 * Rank and percentile of an attempt.
 * Only every student's FIRST submitted attempt counts as the field, so reattempting can't push others down.
 * A reattempt is ranked by where its score would fall among those first attempts.
 */
class RankingService
{
    /** @return array{rank:int, totalCandidates:int, percentile:float} */
    public function for(TestAttempt $attempt): array
    {
        $others = TestAttempt::real()->where('test_id', $attempt->test_id)
            ->where('attempt_no', 1)
            ->whereNotNull('submitted_at')
            ->where('user_id', '!=', $attempt->user_id);

        $total = (clone $others)->count() + 1; // + this student
        $rank = (clone $others)->where('score', '>', $attempt->score)->count() + 1;

        return [
            'rank' => $rank,
            'totalCandidates' => $total,
            'percentile' => round(($total - $rank) / $total * 100, 2),
        ];
    }
}
