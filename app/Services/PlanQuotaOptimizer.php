<?php

namespace App\Services;

use InvalidArgumentException;
use LengthException;
use LogicException;

/**
 * Exact unbounded integer knapsack over plan credit types.
 *
 * Every credit type i has a worst-case creator liability c_i >= 0 and a
 * potential individual Cart value p_i > 0, both in minor units. G[u] is the
 * minimum liability that reaches a potential value of exactly u value units,
 * where one unit is the gcd of all p_i, so no reachable value is skipped.
 *
 * Let b be a credit with the best value per liability (p_b / c_b maximal).
 * Swapping p_b / gcd(p_b, p_j) copies of any other credit j for the same value
 * in copies of b never raises liability, so some optimal solution uses fewer
 * than p_b / gcd(p_b, p_j) copies of each j. Their value is therefore at most
 * W0 = sum_j (p_b / gcd(p_b, p_j) - 1) * p_j, and for every u > W0 an optimal
 * solution contains b: G[u] = G[u - p_b] + c_b. The table stops after one full
 * period beyond W0 and larger values are answered from that period exactly.
 */
class PlanQuotaOptimizer
{
    /** Memory guard for the DP table; exceeding it fails closed instead of truncating. */
    public const MAX_TABLE_STATES = 1_000_000;

    private const INF = PHP_INT_MAX;

    private readonly int $unit;

    /** @var array<int, int> value of each credit type in units */
    private readonly array $values;

    /** @var array<int, int> liability of each credit type in minor units */
    private readonly array $costs;

    private readonly int $horizon;

    private readonly int $end;

    private readonly int $best;

    private readonly bool $periodic;

    /** @var array<int, int> */
    private array $table = [];

    /** @var array<int, int> suffix minimum of the table */
    private array $suffix = [];

    /**
     * @param  array<int, int>  $costs  worst-case liability per credit type (minor units)
     * @param  array<int, int>  $values  potential Cart value per credit type (minor units)
     * @param  int  $maxValueMinor  largest potential value any query may return
     */
    public function __construct(array $costs, array $values, int $maxValueMinor)
    {
        if ($costs === [] || count($costs) !== count($values)) {
            throw new InvalidArgumentException('Every credit type needs a liability and a value.');
        }
        $layout = self::layout(array_values($costs), array_values($values), $maxValueMinor);
        $this->unit = $layout['unit'];
        $this->values = $layout['values'];
        $this->costs = array_values($costs);
        $this->horizon = $layout['horizon'];
        $this->best = $layout['best'];
        $this->periodic = $layout['periodic'];
        $this->end = $layout['end'];

        if ($this->end + 1 > self::MAX_TABLE_STATES) {
            throw new LengthException('The exact quota search space exceeds the configured memory guard.');
        }

        $this->build();
    }

    /**
     * Number of DP states a search with these inputs needs, without building it.
     */
    public static function stateCount(array $costs, array $values, int $maxValueMinor): int
    {
        return self::layout(array_values($costs), array_values($values), $maxValueMinor)['end'] + 1;
    }

    public function valueUnitMinor(): int
    {
        return $this->unit;
    }

    public function horizonMinor(): int
    {
        return $this->horizon * $this->unit;
    }

    public function tableStates(): int
    {
        return $this->end + 1;
    }

    public function bestCredit(): int
    {
        return $this->best;
    }

    /**
     * Largest potential value (minor units, never above $capMinor or the horizon)
     * reachable with worst-case liability <= $budgetMinor; null when the budget is negative.
     */
    public function maxValueWithin(int $budgetMinor, ?int $capMinor = null): ?int
    {
        if ($budgetMinor < 0) {
            return null;
        }
        $cap = $capMinor === null ? $this->horizon : min($this->horizon, intdiv(max(0, $capMinor), $this->unit));

        if ($cap < $this->end) {
            for ($u = $cap; $u > 0; $u--) {
                if ($this->table[$u] <= $budgetMinor) {
                    return $u * $this->unit;
                }
            }

            return 0;
        }

        // The suffix minimum is non-decreasing, so the largest u whose suffix fits
        // the budget is also the largest table value that fits it.
        $low = 0;
        $high = $this->end;
        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            if ($this->suffix[$mid] <= $budgetMinor) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }
        $answer = $low;

        if ($this->periodic && $cap > $this->end) {
            $bestValue = $this->values[$this->best];
            $bestCost = $this->costs[$this->best];
            for ($base = $this->end - $bestValue + 1; $base <= $this->end; $base++) {
                $cost = $this->table[$base];
                if ($cost > $budgetMinor) {
                    continue;
                }
                $copies = intdiv($cap - $base, $bestValue);
                if ($bestCost > 0) {
                    $copies = min($copies, intdiv($budgetMinor - $cost, $bestCost));
                }
                if ($copies > 0) {
                    $answer = max($answer, $base + ($copies * $bestValue));
                }
            }
        }

        return $answer * $this->unit;
    }

    /**
     * Cheapest way to reach a potential value of at least $valueMinor (up to the
     * horizon). With $withValue false only the liability is resolved, which avoids
     * scanning for the value that attains it.
     *
     * @return array{liability_minor: int, value_minor: ?int}|null
     */
    public function minLiabilityForValueAtLeast(int $valueMinor, bool $withValue = true): ?array
    {
        $from = $valueMinor <= 0 ? 0 : intdiv($valueMinor + $this->unit - 1, $this->unit);
        if ($from > $this->horizon) {
            return null;
        }

        $bestCost = self::INF;
        $bestUnits = null;
        if ($from <= $this->end && $this->suffix[$from] !== self::INF) {
            $bestCost = $this->suffix[$from];
            $bestUnits = -1;
            for ($u = $from; $withValue && $u <= $this->end; $u++) {
                if ($this->table[$u] === $bestCost) {
                    $bestUnits = $u;
                    break;
                }
            }
        }

        if ($this->periodic && $this->horizon > $this->end) {
            $bestValue = $this->values[$this->best];
            for ($base = $this->end - $bestValue + 1; $base <= $this->end; $base++) {
                if ($this->table[$base] === self::INF) {
                    continue;
                }
                $copies = max(1, intdiv($from - $base + $bestValue - 1, $bestValue));
                $units = $base + ($copies * $bestValue);
                if ($units > $this->horizon) {
                    continue;
                }
                $cost = $this->table[$base] + ($copies * $this->costs[$this->best]);
                // Table values are always below extension values, so on equal cost they win.
                if ($cost < $bestCost || ($cost === $bestCost && $bestUnits !== -1 && $units < $bestUnits)) {
                    $bestCost = $cost;
                    $bestUnits = $units;
                }
            }
        }

        return $bestUnits === null ? null : [
            'liability_minor' => $bestCost,
            'value_minor' => $withValue ? $bestUnits * $this->unit : null,
        ];
    }

    /**
     * Credit counts that reach exactly $valueMinor at minimum liability.
     *
     * Each step removes one credit whose removal keeps the remainder optimal, so
     * any sequence of choices is optimal. $choose(array $optimalIndexes, array
     * $countsSoFar): int picks among them; by default the first in constructor order.
     *
     * @return array<int, int>
     */
    public function counts(int $valueMinor, ?callable $choose = null): array
    {
        if ($valueMinor < 0 || $valueMinor % $this->unit !== 0) {
            throw new InvalidArgumentException('Value is not reachable in whole credits.');
        }
        $units = intdiv($valueMinor, $this->unit);
        $counts = array_fill(0, count($this->values), 0);
        if ($this->costAt($units) === self::INF) {
            throw new InvalidArgumentException('Value is not reachable in whole credits.');
        }

        while ($units > 0) {
            $cost = $this->costAt($units);
            $optimal = [];
            foreach ($this->values as $index => $value) {
                if ($value > $units) {
                    continue;
                }
                $previous = $this->costAt($units - $value);
                if ($previous !== self::INF && $previous + $this->costs[$index] === $cost) {
                    $optimal[] = $index;
                }
            }
            if ($optimal === []) {
                throw new LogicException('Quota table is inconsistent.');
            }
            $choice = $choose === null ? $optimal[0] : $choose($optimal, $counts);
            if (! in_array($choice, $optimal, true)) {
                throw new LogicException('The credit chooser must return one of the optimal credits.');
            }
            $counts[$choice]++;
            $units -= $this->values[$choice];
        }

        return $counts;
    }

    /**
     * G[u] for any u up to the horizon, using the proven period beyond the table.
     */
    private function costAt(int $units): int
    {
        if ($units <= $this->end) {
            return $this->table[$units];
        }
        $bestValue = $this->values[$this->best];
        $copies = intdiv($units - $this->end + $bestValue - 1, $bestValue);
        $base = $this->table[$units - ($copies * $bestValue)];

        return $base === self::INF ? self::INF : $base + ($copies * $this->costs[$this->best]);
    }

    private function build(): void
    {
        $table = array_fill(0, $this->end + 1, self::INF);
        $table[0] = 0;
        for ($u = 1; $u <= $this->end; $u++) {
            $min = self::INF;
            foreach ($this->values as $index => $value) {
                if ($value > $u) {
                    continue;
                }
                $previous = $table[$u - $value];
                if ($previous !== self::INF && $previous + $this->costs[$index] < $min) {
                    $min = $previous + $this->costs[$index];
                }
            }
            $table[$u] = $min;
        }

        $suffix = $table;
        for ($u = $this->end - 1; $u >= 0; $u--) {
            if ($suffix[$u + 1] < $suffix[$u]) {
                $suffix[$u] = $suffix[$u + 1];
            }
        }

        $this->table = $table;
        $this->suffix = $suffix;
    }

    /**
     * @return array{unit: int, values: array<int, int>, horizon: int, best: int, periodic: bool, end: int}
     */
    private static function layout(array $costs, array $values, int $maxValueMinor): array
    {
        if ($costs === [] || count($costs) !== count($values)) {
            throw new InvalidArgumentException('Every credit type needs a liability and a value.');
        }
        $unit = 0;
        foreach ($costs as $index => $cost) {
            if (! is_int($cost) || ! is_int($values[$index]) || $cost < 0 || $values[$index] <= 0) {
                throw new InvalidArgumentException('Liabilities must be non-negative and values positive integers.');
            }
            $unit = self::gcd($unit, $values[$index]);
        }
        $units = array_map(fn (int $value) => intdiv($value, $unit), $values);
        $horizon = max(0, intdiv($maxValueMinor, $unit));

        // Best value per liability; among equals, the one with the shortest period.
        $best = 0;
        $bestSpan = null;
        foreach ($units as $index => $value) {
            $comparison = ($value * $costs[$best]) <=> ($units[$best] * $costs[$index]);
            $span = self::periodSpan($units, $index);
            if ($comparison > 0 || ($comparison === 0 && ($bestSpan === null || $span < $bestSpan))) {
                $best = $index;
                $bestSpan = $span;
            }
        }
        $periodic = $horizon > $bestSpan;

        return [
            'unit' => $unit,
            'values' => $units,
            'horizon' => $horizon,
            'best' => $best,
            'periodic' => $periodic,
            'end' => $periodic ? $bestSpan : $horizon,
        ];
    }

    /**
     * W0 + p_b: the table length after which G is periodic in the candidate credit.
     */
    private static function periodSpan(array $units, int $candidate): int
    {
        $span = $units[$candidate];
        foreach ($units as $index => $value) {
            if ($index !== $candidate) {
                $span += (intdiv($units[$candidate], self::gcd($units[$candidate], $value)) - 1) * $value;
            }
        }

        return $span;
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return abs($a);
    }
}
