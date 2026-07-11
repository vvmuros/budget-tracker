<?php

namespace App\Http\Controllers;

use App\Models\BudgetData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BudgetController extends Controller
{
    private const PERIODIC_KEYS = ['expense-items', 'income-items', 'expense-rates'];

    public function index()
    {
        return view('budget.index');
    }

    public function fetch(Request $request)
    {
        $period = $request->query('period', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $period) === 1, 422, 'Nevažeći period.');

        $user = $request->user();

        $result = [];

        $savings = $user->budgetData()->where('key', 'savings-items')->where('period', 'global')->value('value');
        if ($savings !== null) {
            $result['savings-items'] = $savings;
        }

        $exactRows = $user->budgetData()->whereIn('key', self::PERIODIC_KEYS)->where('period', $period)->pluck('value', 'key');
        foreach ($exactRows as $key => $value) {
            $result[$key] = $value;
        }

        $missingKeys = array_diff(self::PERIODIC_KEYS, $exactRows->keys()->all());
        $isNewPeriod = count($missingKeys) > 0;
        $previousNet = null;

        if ($isNewPeriod) {
            $templatePeriod = $user->budgetData()
                ->whereIn('key', self::PERIODIC_KEYS)
                ->where('period', '<', $period)
                ->max('period');

            if ($templatePeriod) {
                $templateRows = $user->budgetData()
                    ->whereIn('key', self::PERIODIC_KEYS)
                    ->where('period', $templatePeriod)
                    ->pluck('value', 'key');

                foreach ($missingKeys as $key) {
                    if ($templateRows->has($key)) {
                        $result[$key] = $templateRows[$key];
                    }
                }

                $previousNet = $this->calculateNet($templateRows);
            }
        }

        return response()->json([
            'data' => $result,
            'period' => $period,
            'is_new_period' => $isNewPeriod,
            'previous_net' => $previousNet,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $period = $data['key'] === 'savings-items'
            ? 'global'
            : ($data['period'] ?? now()->format('Y-m'));

        BudgetData::updateOrCreate(
            ['user_id' => $request->user()->id, 'key' => $data['key'], 'period' => $period],
            ['value' => $data['value'] ?? '']
        );

        return response()->json(['ok' => true]);
    }

    private function calculateNet(Collection $rows): ?float
    {
        $income = json_decode($rows->get('income-items', '[]'), true) ?: [];
        $expenses = json_decode($rows->get('expense-items', '[]'), true) ?: [];
        $rates = json_decode($rows->get('expense-rates', '{}'), true) ?: ['usd' => 0, 'eur' => 0];

        $toRsd = function ($amount, $currency) use ($rates) {
            return match ($currency) {
                'USD' => $amount * ($rates['usd'] ?? 0),
                'EUR' => $amount * ($rates['eur'] ?? 0),
                default => $amount,
            };
        };

        $sumActive = function ($items) use ($toRsd) {
            return collect($items)
                ->filter(fn ($it) => $it['active'] ?? false)
                ->sum(fn ($it) => $toRsd($it['amount'] ?? 0, $it['currency'] ?? 'RSD'));
        };

        return $sumActive($income) - $sumActive($expenses);
    }
}
