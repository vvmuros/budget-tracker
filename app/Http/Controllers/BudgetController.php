<?php

namespace App\Http\Controllers;

use App\Models\BudgetData;
use App\Models\User;
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

        $isNewPeriod = false;
        $templatePeriod = null;
        $templateValues = [];

        foreach (self::PERIODIC_KEYS as $key) {
            $resolved = $this->resolveEffectiveValue($user, $key, $period);
            if (! $resolved) {
                continue;
            }

            $result[$key] = $resolved['value'];

            if (! $resolved['is_exact']) {
                $isNewPeriod = true;
                $templateValues[$key] = $resolved['value'];
                if (! $templatePeriod || $resolved['period'] > $templatePeriod) {
                    $templatePeriod = $resolved['period'];
                }
            }
        }

        $previousNet = null;
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

    public function yearly(Request $request)
    {
        $user = $request->user();
        $currentPeriod = now()->format('Y-m');
        $yearStart = now()->format('Y').'-01';

        $firstPeriod = $user->budgetData()->whereIn('key', self::PERIODIC_KEYS)->min('period');

        if (! $firstPeriod) {
            return response()->json(['months' => []]);
        }

        $startPeriod = max($firstPeriod, $yearStart);

        $months = [];
        $cursor = $startPeriod;

        while ($cursor <= $currentPeriod) {
            $income = $this->resolveEffectiveValue($user, 'income-items', $cursor);
            $expense = $this->resolveEffectiveValue($user, 'expense-items', $cursor);
            $rates = $this->resolveEffectiveValue($user, 'expense-rates', $cursor);

            $ratesArr = json_decode($rates['value'] ?? '{}', true) ?: ['usd' => 0, 'eur' => 0];

            $months[] = [
                'period' => $cursor,
                'income' => $this->sumActiveItems($income['value'] ?? '[]', $ratesArr),
                'expense' => $this->sumActiveItems($expense['value'] ?? '[]', $ratesArr),
            ];

            $cursor = $this->incrementPeriod($cursor);
        }

        foreach ($months as &$month) {
            $month['net'] = $month['income'] - $month['expense'];
        }

        return response()->json(['months' => $months]);
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
            'expense_categories' => ['required', 'array'],
            'expense_categories.*' => ['string'],
            'savings_categories' => ['required', 'array'],
            'savings_categories.*' => ['string'],
        ]);

        $categories = array_values(array_unique(array_merge($data['expense_categories'], $data['savings_categories'])));

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
                'category' => ['type' => 'STRING', 'enum' => $categories],
            ],
            'required' => ['action', 'name', 'amount', 'currency', 'freq', 'category'],
        ];

        $message = $data['message'];
        $categoryList = implode(', ', $categories);
        $lang = $request->cookie('lang', 'sr');

        if ($lang === 'en') {
            $prompt = <<<PROMPT
            The user wrote a message about their finances: "{$message}"

            Determine whether the user wants to:
            - add an expense (add_expense)
            - add income (add_income)
            - add a savings/asset item (add_saving)
            - or the message is unclear (unclear)

            If clear, extract the item name (name), amount (amount, number only),
            currency (currency: RSD/EUR/USD, default RSD if not stated), and for
            expense/income the frequency (freq: 1=monthly, 2=every 2 months,
            3=every 3 months, 0=one-time).

            Important for freq: a spontaneous mention of a single purchase (coffee,
            taxi, fuel, food, a gift, etc.) is a ONE-TIME expense (freq=0) — that is
            the default. Only use monthly/every-2-months/every-3-months if the
            message explicitly says it repeats ("monthly", "every month",
            "subscription", "membership", "installment", "rent") or clearly
            describes a recurring obligation (bill, membership, loan installment).
            Don't assume something is monthly just because frequency wasn't stated.
            Savings items don't need freq.

            If add_expense or add_saving, pick the best matching category
            (category) from this list based on the item name: {$categoryList}. If
            none clearly match, use "Ostalo". For add_income or unclear, set
            category to "Ostalo".
            PROMPT;
        } else {
            $prompt = <<<PROMPT
            Korisnik je napisao poruku o svojim finansijama na srpskom: "{$message}"

            Prepoznaj da li korisnik želi da:
            - doda trošak (add_expense)
            - doda primanje/prihod (add_income)
            - doda stavku štednje/imovine (add_saving)
            - ili poruka nije jasna (unclear)

            Ako je jasna, izvuci naziv stavke (name), iznos (amount, samo broj), valutu
            (currency: RSD/EUR/USD, podrazumevano RSD ako nije rečeno), i za trošak/primanje
            učestalost (freq: 1=mesečno, 2=na 2 meseca, 3=na 3 meseca, 0=jednokratno).

            Važno za freq: spontan pomen pojedinačne kupovine (kafa, taxi, gorivo, hrana,
            poklon i slično) je JEDNOKRATAN trošak (freq=0) — to je podrazumevana vrednost.
            Koristi mesečno/na 2 meseca/na 3 meseca SAMO ako poruka eksplicitno kaže da se
            ponavlja ("mesečno", "svaki mesec", "na dva meseca", "pretplata", "članarina",
            "rata", "kirija", "stanarina") ili opisuje očigledno ponavljajuću obavezu
            (račun, članarina, rata kredita). Ne pretpostavljaj automatski da je nešto
            mesečno samo zato što učestalost nije eksplicitno navedena. Za štednju freq
            nije potreban.

            Ako je add_expense ili add_saving, izaberi najprikladniju kategoriju (category)
            iz ove liste na osnovu naziva stavke: {$categoryList}. Ako nijedna jasno ne
            odgovara, koristi "Ostalo". Za add_income ili unclear, postavi category na
            "Ostalo".
            PROMPT;
        }

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

    public function receipt(Request $request, GeminiClient $gemini)
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'max:6000000'],
            'mime_type' => ['required', 'string', 'in:image/jpeg,image/png,image/webp'],
            'expense_categories' => ['required', 'array'],
            'expense_categories.*' => ['string'],
        ]);

        $categories = array_values(array_unique($data['expense_categories']));
        $categoryList = implode(', ', $categories);
        $lang = $request->cookie('lang', 'sr');

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'action' => ['type' => 'STRING', 'enum' => ['add_expense', 'unclear']],
                'name' => ['type' => 'STRING'],
                'amount' => ['type' => 'NUMBER'],
                'currency' => ['type' => 'STRING', 'enum' => ['RSD', 'EUR', 'USD']],
                'category' => ['type' => 'STRING', 'enum' => $categories],
            ],
            'required' => ['action', 'name', 'amount', 'currency', 'category'],
        ];

        if ($lang === 'en') {
            $prompt = <<<PROMPT
            This is a photo of a store receipt. Read the store name or main item
            (name), the total amount due (amount, number only), the currency
            (currency: RSD/EUR/USD, default RSD) and the best matching category
            (category) from this list: {$categoryList}. If none clearly match, use
            "Ostalo".

            If the image is unreadable or doesn't look like a receipt, return
            action=unclear. Otherwise return action=add_expense.
            PROMPT;
        } else {
            $prompt = <<<PROMPT
            Ovo je slika računa iz prodavnice. Pročitaj naziv prodavnice ili glavnu stavku
            (name), ukupan iznos za plaćanje (amount, samo broj), valutu (currency:
            RSD/EUR/USD, podrazumevano RSD) i najprikladniju kategoriju (category) iz ove
            liste: {$categoryList}. Ako nijedna jasno ne odgovara, koristi "Ostalo".

            Ako slika nije čitljiva ili ne liči na račun, vrati action=unclear.
            Inače vrati action=add_expense.
            PROMPT;
        }

        try {
            $raw = $gemini->generate($prompt, $schema, [
                ['mime_type' => $data['mime_type'], 'data' => $data['image']],
            ]);
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

        $lang = $request->cookie('lang', 'sr');

        if ($lang === 'en') {
            $prompt = <<<PROMPT
            You are a financial assistant. Here is a summary of the user's monthly
            budget (period {$data['period']}):

            Total income: {$data['income_total']} RSD
            Total expenses: {$data['expense_total']} RSD
            Net: {$data['net']} RSD

            Active expenses:
            {$expenseLines}

            Give a short, concrete piece of advice in English (2-4 sentences) about
            this spending — call out something specific from the expense list if
            possible, not generic phrases.
            PROMPT;
        } else {
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
        }

        try {
            $tip = $gemini->generate($prompt);
        } catch (\Throwable) {
            $message = $lang === 'en' ? 'Analysis is currently unavailable.' : 'Analiza trenutno nije dostupna.';

            return response()->json(['error' => $message], 502);
        }

        return response()->json(['tip' => trim($tip)]);
    }

    private function calculateNet(Collection $rows): ?float
    {
        $rates = json_decode($rows->get('expense-rates', '{}'), true) ?: ['usd' => 0, 'eur' => 0];

        return $this->sumActiveItems($rows->get('income-items', '[]'), $rates)
            - $this->sumActiveItems($rows->get('expense-items', '[]'), $rates);
    }

    private function sumActiveItems(string $json, array $rates): float
    {
        $items = json_decode($json, true) ?: [];

        return collect($items)
            ->filter(fn ($it) => $it['active'] ?? false)
            ->sum(fn ($it) => $this->toRsd($it['amount'] ?? 0, $it['currency'] ?? 'RSD', $rates));
    }

    private function toRsd(float $amount, string $currency, array $rates): float
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
    private function resolveEffectiveValue(User $user, string $key, string $period): ?array
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

    private function incrementPeriod(string $period): string
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
