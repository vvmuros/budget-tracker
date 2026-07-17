<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRateSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExchangeRateController extends Controller
{
    public function latest()
    {
        $snapshot = ExchangeRateSnapshot::orderByDesc('date')->first();

        return response()->json([
            'date' => $snapshot?->date?->toDateString(),
            'usd' => $snapshot?->usd,
            'eur' => $snapshot?->eur,
        ]);
    }

    public function history(Request $request)
    {
        $range = $request->query('range', '7d');
        abort_unless(in_array($range, ['day', '7d', 'week', 'month', 'year'], true), 422);

        $since = match ($range) {
            'day' => now()->subDays(14),
            '7d' => now()->subDays(7),
            'week' => now()->subWeeks(12),
            'month' => now()->subMonths(12),
            'year' => now()->subYears(5),
        };

        $snapshots = ExchangeRateSnapshot::where('date', '>=', $since->toDateString())
            ->orderBy('date')
            ->get(['date', 'usd', 'eur']);

        if (in_array($range, ['day', '7d'], true)) {
            $points = $snapshots->map(fn ($s) => [
                'label' => $s->date->format('d.m'),
                'usd' => $s->usd,
                'eur' => $s->eur,
            ]);

            return response()->json(['points' => $points->values()]);
        }

        // week/month/year: one averaged point per bucket, since we only ever
        // have one snapshot per calendar day to begin with.
        $groupFormat = match ($range) {
            'week' => fn (Carbon $d) => $d->startOfWeek()->format('Y-m-d'),
            'month' => fn (Carbon $d) => $d->format('Y-m'),
            'year' => fn (Carbon $d) => $d->format('Y'),
        };

        $labelFormat = match ($range) {
            'week' => fn (Carbon $d) => $d->format('d.m'),
            'month' => fn (Carbon $d) => $d->format('m.Y'),
            'year' => fn (Carbon $d) => $d->format('Y'),
        };

        $points = $snapshots
            ->groupBy(fn ($s) => $groupFormat($s->date))
            ->map(function ($group) use ($labelFormat) {
                return [
                    'label' => $labelFormat($group->first()->date),
                    'usd' => round($group->avg('usd'), 4),
                    'eur' => round($group->avg('eur'), 4),
                ];
            })
            ->values();

        return response()->json(['points' => $points]);
    }
}
