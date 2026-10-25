<?php

namespace Tests\Unit;

use App\Services\PlanQuotaOptimizer;
use LengthException;
use PHPUnit\Framework\TestCase;

class PlanQuotaOptimizerTest extends TestCase
{
    public function test_matches_brute_force_on_random_instances_including_periodic_tables(): void
    {
        mt_srand(20261025);
        $periodic = 0;

        for ($trial = 0; $trial < 300; $trial++) {
            $types = mt_rand(1, 4);
            $costs = [];
            $values = [];
            for ($i = 0; $i < $types; $i++) {
                $values[] = mt_rand(2, 12) * (mt_rand(0, 1) ? 5 : 1);
                $costs[] = mt_rand(0, 15);
            }
            $budget = mt_rand(0, 60);
            $horizon = mt_rand(0, 1) ? 150 : mt_rand(10, 90);
            $cap = mt_rand(0, 100);
            $target = mt_rand(0, 80);
            $optimizer = new PlanQuotaOptimizer($costs, $values, $horizon);
            if ($optimizer->tableStates() < intdiv($horizon, $optimizer->valueUnitMinor()) + 1) {
                $periodic++;
            }

            [$best, $bestCapped, $cheapestCover] = $this->bruteForce($costs, $values, $budget, $horizon, $cap, $target);
            $context = json_encode(compact('costs', 'values', 'budget', 'horizon', 'cap', 'target'));

            $this->assertSame($best, $optimizer->maxValueWithin($budget), $context);
            $this->assertSame($bestCapped, $optimizer->maxValueWithin($budget, $cap), $context);
            $this->assertSame($cheapestCover, $optimizer->minLiabilityForValueAtLeast($target)['liability_minor'] ?? null, $context);
            $this->assertSame($cheapestCover, $optimizer->minLiabilityForValueAtLeast($target, false)['liability_minor'] ?? null, $context);

            $counts = $optimizer->counts($best);
            $this->assertSame($best, $this->dot($counts, $values), $context);
            $this->assertLessThanOrEqual($budget, $this->dot($counts, $costs), $context);
        }

        $this->assertGreaterThan(50, $periodic, 'The periodic extension must be exercised.');
    }

    public function test_large_values_use_the_proven_period_instead_of_a_huge_table(): void
    {
        // Seeded Scemory tiers: payout caps are 90% of maximum prices.
        $costs = [180, 450, 450, 1350, 1800, 4500];
        $values = [200, 500, 500, 1500, 2000, 5000];
        $optimizer = new PlanQuotaOptimizer($costs, $values, 200_000_000);

        $this->assertLessThan(100, $optimizer->tableStates());
        // $880,000 of liability buys exactly $977,777 of value at $0.90 per $1.00.
        $this->assertSame(97_777_700, $optimizer->maxValueWithin(88_000_000));
        $counts = $optimizer->counts(97_777_700);
        $this->assertSame(97_777_700, $this->dot($counts, $values));
        $this->assertSame(87_999_930, $this->dot($counts, $costs));
    }

    public function test_reconstruction_only_uses_optimal_moves_chosen_by_the_caller(): void
    {
        $optimizer = new PlanQuotaOptimizer([180, 450], [200, 500], 10_000);
        $preferSecond = fn (array $optimal) => in_array(1, $optimal, true) ? 1 : $optimal[0];

        $this->assertSame([5, 0], $optimizer->counts(1000));
        $this->assertSame([0, 2], $optimizer->counts(1000, $preferSecond));
        $this->expectException(\LogicException::class);
        $optimizer->counts(1000, fn () => 7);
    }

    public function test_memory_guard_fails_closed(): void
    {
        $this->expectException(LengthException::class);
        new PlanQuotaOptimizer([1999, 4501], [2003, 4999], 3_000_000_000);
    }

    private function bruteForce(array $costs, array $values, int $budget, int $horizon, int $cap, int $target): array
    {
        $best = 0;
        $bestCapped = 0;
        $cheapestCover = null;
        $walk = function (int $index, int $value, int $cost) use (&$walk, $costs, $values, $budget, $horizon, $cap, $target, &$best, &$bestCapped, &$cheapestCover) {
            if ($value > $horizon) {
                return;
            }
            if ($index === count($values)) {
                if ($cost <= $budget) {
                    $best = max($best, $value);
                    if ($value <= $cap) {
                        $bestCapped = max($bestCapped, $value);
                    }
                }
                if ($value >= $target && ($cheapestCover === null || $cost < $cheapestCover)) {
                    $cheapestCover = $cost;
                }

                return;
            }
            for ($n = 0; $value + ($n * $values[$index]) <= $horizon; $n++) {
                $walk($index + 1, $value + ($n * $values[$index]), $cost + ($n * $costs[$index]));
            }
        };
        $walk(0, 0, 0);

        return [$best, $bestCapped, $cheapestCover];
    }

    private function dot(array $counts, array $weights): int
    {
        return array_sum(array_map(fn ($n, $w) => $n * $w, $counts, $weights));
    }
}
