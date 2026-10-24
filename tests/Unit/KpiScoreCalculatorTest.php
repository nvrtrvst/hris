<?php

namespace Tests\Unit;

use App\Services\KpiScoreCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class KpiScoreCalculatorTest extends TestCase
{
    public function test_higher_is_better_is_capped_at_target(): void
    {
        $this->assertSame(80.0, KpiScoreCalculator::score(8, 10, 'higher'));
        $this->assertSame(100.0, KpiScoreCalculator::score(12, 10, 'higher'));
    }

    public function test_lower_is_better_scores_target_or_better_as_full(): void
    {
        $this->assertSame(100.0, KpiScoreCalculator::score(4, 5, 'lower'));
        $this->assertSame(50.0, KpiScoreCalculator::score(10, 5, 'lower'));
    }

    public function test_rubric_maps_four_levels_to_score(): void
    {
        $this->assertSame(0.0, KpiScoreCalculator::rubricScore(1));
        $this->assertSame(60.0, KpiScoreCalculator::rubricScore(2));
        $this->assertSame(85.0, KpiScoreCalculator::rubricScore(3));
        $this->assertSame(100.0, KpiScoreCalculator::rubricScore(4));
    }

    public function test_weighted_score_ignores_not_assessed_items_and_renormalizes(): void
    {
        $this->assertSame(83.33, KpiScoreCalculator::weightedScore([
            ['score' => 100, 'weight' => 50],
            ['score' => null, 'weight' => 25],
            ['score' => 50, 'weight' => 25],
        ]));
        $this->assertNull(KpiScoreCalculator::weightedScore([
            ['score' => null, 'weight' => 100],
        ]));
    }

    public function test_invalid_direction_and_rubric_level_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KpiScoreCalculator::score(1, 2, 'sideways');
    }
}
