<?php

namespace App\Http\Controllers;

use App\Models\BudgetData;
use App\Services\GeminiClient;
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
        $templatePeriod = null;
        $templateValues = [];

        foreach ($missingKeys as $key) {
            $row = $user->budgetData()
                ->where('key', $key)
                ->where('period', '<', $period)
                ->orderByDesc('period')
                ->first();

            if ($row) {
                $result[$key] = $row->value;
                $templateValues[$key] = $row->value;
                if (! $templatePeriod || $row->period > $templatePeriod) {
                    $templatePeriod = $row->period;
                }
            }
        }

        if ($isNewPeriod && isset($templateValues['income-items'], $templateValues['expense-items'])) {
            $previousNet = $this->calculateNet(collect([
                'income-items' => $templateValues['income-items'],
                'expense-items' => $templateValues['expense-items'],
                'expense-rates' => $templateValues['expense-rates'] ?? '{}',
            ]));
        }

        return response()->json([
            'data' => $result,
            'period' => $period,
            'is_new_period' => $isNewPeriod,
            'previous_net' => $previousNet,
            'template_period' => $templatePeriod,
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

    public function chat(Request $request, GeminiClient $gemini)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'action' => [
                    'type' => 'STRING',
                    'enum' => ['add_expense', 'add_income', 'add_saving', 'unclear'],
                ],
                'name' => ['type' => 'STRING'],
                'amount' => ['type' => 'NUMBER'],
                'currency' => ['type' => 'STRING', 'enum' => ['RSD', 'EUR', 'USD']],
                'freq' => ['type' => 'INTEGER'],
            ],
            'required' => ['action'],
        ];

        $message = $data['message'];
        $prompt = <<<PROMPT
        Korisnik je napisao poruku o svojim finansijama na srpskom: "{$message}"

        Prepoznaj da li korisnik želi da:
        - doda trošak (add_expense)
        - doda primanje/prihod (add_income)
        - doda stavku štednje/imovine (add_saving)
        - ili poruka nije jasna (unclear)

        Ako je jasna, izvuci naziv stavke (name), iznos (amount, samo broj), valutu
        (currency: RSD/EUR/USD, podrazumevano RSD ako nije rečeno), i za trošak/primanje
        učestalost (freq: 1=mesečno, 2=na 2 meseca, 3=na 3 meseca, 0=jednokratno;
        podrazumevano 1 ako nije rečeno). Za štednju freq nije potreban.
        PROMPT;

        try {
            $raw = $gemini->generate($prompt, $schema);
            $parsed = json_decode($raw, true);
        } catch (\Throwable) {
            return response()->json(['action' => 'unclear']);
        }

        if (! $parsed || ! isset($parsed['action'])) {
            return response()->json(['action' => 'unclear']);
        }

        return response()->json($parsed);
    }

    public function analyze(Request $request, GeminiClient $gemini)
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'income_total' => ['required', 'numeric'],
            'expense_total' => ['required', 'numeric'],
            'net' => ['required', 'numeric'],
            'expenses' => ['array'],
            'expenses.*.name' => ['required_with:expenses', 'string'],
            'expenses.*.amount' => ['required_with:expenses', 'numeric'],
            'expenses.*.currency' => ['required_with:expenses', 'string'],
        ]);

        $expenseLines = collect($data['expenses'] ?? [])
            ->map(fn ($it) => "- {$it['name']}: {$it['amount']} {$it['currency']}")
            ->implode("\n");

        $prompt = <<<PROMPT
        Ti si finansijski asistent. Evo sažetka mesečnog budžeta korisnika (period {$data['period']}):

        Ukupna primanja: {$data['income_total']} RSD
        Ukupni troškovi: {$data['expense_total']} RSD
        Neto: {$data['net']} RSD

        Aktivni troškovi:
        {$expenseLines}

        Daj kratak, konkretan savet na srpskom (2-4 rečenice) o ovoj potrošnji — istakni
        nešto specifično iz liste troškova ako je moguće, ne generičke fraze.
        PROMPT;

        try {
            $tip = $gemini->generate($prompt);
        } catch (\Throwable) {
            return response()->json(['error' => 'Analiza trenutno nije dostupna.'], 502);
        }

        return response()->json(['tip' => trim($tip)]);
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
