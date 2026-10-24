<?php

namespace App\Services;

use InvalidArgumentException;

class KpiScoreCalculator
{
    public static function score(float $actual, float $target, string $direction): float
    {
        if (! in_array($direction, ['higher', 'lower'], true)) {
            throw new InvalidArgumentException('Direction must be higher or lower.');
        }
        if ($actual < 0 || $target < 0 || ($direction === 'higher' && $target === 0.0)) {
            throw new InvalidArgumentException('Actual and target must be valid non-negative values.');
        }

        if ($direction === 'lower') {
            return $actual <= $target || $actual === 0.0
                ? 100.0
                : round($target * 100 / $actual, 2);
        }

        return round(min(100, $actual * 100 / $target), 2);
    }

    public static function rubricScore(int $level): float
    {
        return match ($level) {
            1 => 0.0,
            2 => 60.0,
            3 => 85.0,
            4 => 100.0,
            default => throw new InvalidArgumentException('Rubric level must be between 1 and 4.'),
        };
    }

    /** @param array<int, array{score: ?float, weight: float}> $items */
    public static function weightedScore(array $items): ?float
    {
        $weighted = 0.0;
        $weightTotal = 0.0;

        foreach ($items as $item) {
            $weight = (float) $item['weight'];
            if ($weight < 0 || $weight > 100) {
                throw new InvalidArgumentException('Weight must be between 0 and 100.');
            }
            if ($item['score'] === null || $weight === 0.0) {
                continue;
            }
            if ($item['score'] < 0 || $item['score'] > 100) {
                throw new InvalidArgumentException('Score must be between 0 and 100.');
            }

            $weighted += $item['score'] * $weight;
            $weightTotal += $weight;
        }

        return $weightTotal > 0 ? round($weighted / $weightTotal, 2) : null;
    }
}
