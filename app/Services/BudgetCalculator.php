<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shared math for turning saved budget_data rows into actual RSD totals —
 * used by BudgetController (book view, yearly analysis) and PushController
 * (monthly savings reminder), so both agree on what "active this month"
 * actually means.
 */
class BudgetCalculator
{
    /**
     * Falls back to these when a user has never saved a rate (e.g. no
     * expense-rates row exists yet for any period) — matches the frontend's
     * own default so foreign-currency items don't silently price at 0 RSD
     * server-side while the book view shows them normally.
     */
    public const DEFAULT_RATES = ['usd' => 102.76, 'eur' => 117.36];

    public function calculateNet(Collection $rows, ?string $period = null): ?float
    {
        $rates = json_decode($rows->get('expense-rates', '{}'), true) ?: self::DEFAULT_RATES;

        return $this->sumActiveItems($rows->get('income-items', '[]'), $rates, $period)
            - $this->sumActiveItems($rows->get('expense-items', '[]'), $rates, $period);
    }

    public function sumActiveItems(string $json, array $rates, ?string $period = null): float
    {
        $items = json_decode($json, true) ?: [];

        return collect($items)
            ->filter(fn ($it) => $this->isItemActive($it, $period) && empty($it['paidFromSavings']))
            ->sum(function ($it) use ($rates) {
                $currency = $it['currency'] ?? 'RSD';
                $amount = $this->toRsd($it['amount'] ?? 0, $currency, $rates);
                $diverted = min($this->toRsd($it['savingsDiverted'] ?? 0, $currency, $rates), $amount);

                return $amount - $diverted;
            });
    }

    public function sumByCategory(string $json, array $rates, ?string $period = null): array
    {
        $items = json_decode($json, true) ?: [];

        $totals = [];
        foreach ($items as $it) {
            if (! $this->isItemActive($it, $period)) {
                continue;
            }
            $cat = $it['category'] ?? 'Ostalo';
            $totals[$cat] = ($totals[$cat] ?? 0) + $this->toRsd($it['amount'] ?? 0, $it['currency'] ?? 'RSD', $rates);
        }

        return $totals;
    }

    /**
     * An item counts as active if its own "active" flag is on, and — for
     * expense items with an end month — the given period hasn't passed it yet.
     */
    public function isItemActive(array $item, ?string $period): bool
    {
        if (! ($item['active'] ?? false)) {
            return false;
        }

        if ($period !== null && ! empty($item['endPeriod']) && $period > $item['endPeriod']) {
            return false;
        }

        if ($period !== null && ! $this->isDueInPeriod($item, $period)) {
            return false;
        }

        return true;
    }

    /**
     * For a custom "every N months" item (freq > 1) with a due-month anchor,
     * only the months that are an exact multiple of N away from the anchor
     * actually count — e.g. paid in July, every 2 months, means September and
     * November are due but August and October aren't.
     */
    public function isDueInPeriod(array $item, string $period): bool
    {
        $freq = (int) ($item['freq'] ?? 1);
        if ($freq <= 1) {
            return true;
        }

        $anchor = $item['dueAnchor'] ?? null;
        if (! $anchor) {
            return true;
        }

        $diff = $this->monthsBetweenPeriods($anchor, $period);

        return $diff >= 0 && $diff % $freq === 0;
    }

    public function monthsBetweenPeriods(string $from, string $to): int
    {
        [$fy, $fm] = array_map('intval', explode('-', $from));
        [$ty, $tm] = array_map('intval', explode('-', $to));

        return ($ty - $fy) * 12 + ($tm - $fm);
    }

    public function toRsd(float $amount, string $currency, array $rates): float
    {
        return match ($currency) {
            'USD' => $amount * ($rates['usd'] ?? 0),
            'EUR' => $amount * ($rates['eur'] ?? 0),
            default => $amount,
        };
    }

    /**
     * Finds the effective value for a key at a given period: the exact row if it
     * exists, otherwise the nearest prior period's row (used as a carry-forward
     * template). Returns null if nothing exists at or before the period.
     */
    public function resolveEffectiveValue(User $user, string $key, string $period): ?array
    {
        $exact = $user->budgetData()->where('key', $key)->where('period', $period)->first();
        if ($exact) {
            return ['value' => $exact->value, 'period' => $period, 'is_exact' => true];
        }

        $row = $user->budgetData()
            ->where('key', $key)
            ->where('period', '<', $period)
            ->orderByDesc('period')
            ->first();

        if ($row) {
            return ['value' => $row->value, 'period' => $row->period, 'is_exact' => false];
        }

        return null;
    }

    /**
     * Strips one-time items (freq === 0) from a carried-forward template —
     * they belong only to the month they were entered in, unlike recurring
     * items which are meant to keep showing up until removed.
     */
    public function stripOneTimeItems(string $json): string
    {
        $items = json_decode($json, true);
        if (! is_array($items)) {
            return $json;
        }

        $filtered = array_values(array_filter(
            $items,
            fn ($item) => is_array($item) && (int) ($item['freq'] ?? 1) !== 0
        ));

        return json_encode($filtered);
    }

    public function incrementPeriod(string $period): string
    {
        [$year, $month] = array_map('intval', explode('-', $period));
        $month++;
        if ($month > 12) {
            $month = 1;
            $year++;
        }

        return sprintf('%04d-%02d', $year, $month);
    }
}
