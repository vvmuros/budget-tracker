<?php

namespace App\Http\Controllers;

use App\Models\BudgetData;
use App\Models\User;
use App\Services\BudgetCalculator;
use App\Services\GeminiClient;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    private const PERIODIC_KEYS = ['expense-items', 'income-items', 'expense-rates'];

    public function __construct(private BudgetCalculator $calc)
    {
    }

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
            $resolved = $this->calc->resolveEffectiveValue($user, $key, $period);
            if (! $resolved) {
                continue;
            }

            $displayValue = $resolved['value'];
            if (! $resolved['is_exact'] && in_array($key, ['expense-items', 'income-items'], true)) {
                // A carried-forward month should only inherit recurring items —
                // a one-time purchase from a past month has no business
                // reappearing in a month that never explicitly saved it.
                $displayValue = $this->calc->stripOneTimeItems($displayValue);
            }
            $result[$key] = $displayValue;

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
            $previousNet = $this->calc->calculateNet(collect([
                'income-items' => $templateValues['income-items'],
                'expense-items' => $templateValues['expense-items'],
                'expense-rates' => $templateValues['expense-rates'] ?? '{}',
            ]), $templatePeriod);
        }

        return response()->json([
            'data' => $result,
            'period' => $period,
            'is_new_period' => $isNewPeriod,
            'previous_net' => $previousNet,
            'template_period' => $templatePeriod,
        ])->header('Cache-Control', 'no-store, must-revalidate');
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
            $income = $this->calc->resolveEffectiveValue($user, 'income-items', $cursor);
            $expense = $this->calc->resolveEffectiveValue($user, 'expense-items', $cursor);
            $rates = $this->calc->resolveEffectiveValue($user, 'expense-rates', $cursor);

            $ratesArr = json_decode($rates['value'] ?? '{}', true) ?: ['usd' => 0, 'eur' => 0];

            $incomeValue = $income && ! $income['is_exact']
                ? $this->calc->stripOneTimeItems($income['value'])
                : ($income['value'] ?? '[]');
            $expenseValue = $expense && ! $expense['is_exact']
                ? $this->calc->stripOneTimeItems($expense['value'])
                : ($expense['value'] ?? '[]');

            $months[] = [
                'period' => $cursor,
                'income' => $this->calc->sumActiveItems($incomeValue, $ratesArr, $cursor),
                'expense' => $this->calc->sumActiveItems($expenseValue, $ratesArr, $cursor),
                'categories' => $this->calc->sumByCategory($expenseValue, $ratesArr, $cursor),
            ];

            $cursor = $this->calc->incrementPeriod($cursor);
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

        if (in_array($data['key'], ['expense-items', 'income-items'], true) && $period !== 'global') {
            $this->mirrorRecurringItemsForward($request->user(), $data['key'], $period, $data['value'] ?? '');
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Recurring items (freq !== 0) are meant to look the same everywhere once
     * edited — propagate name/amount/currency/freq/category/endPeriod to any
     * later month that already has its own saved row, without touching that
     * row's own "active" flag (that stays per-month on purpose, e.g. for a
     * quarterly bill you skip some months).
     */
    private function mirrorRecurringItemsForward(User $user, string $key, string $period, string $json): void
    {
        $items = json_decode($json, true);
        if (! is_array($items)) {
            return;
        }

        $sharedFields = ['name', 'amount', 'currency', 'freq', 'category', 'endPeriod', 'dueAnchor'];
        $recurringById = [];
        $recurringByName = [];

        foreach ($items as $item) {
            if (! is_array($item) || (int) ($item['freq'] ?? 0) === 0) {
                continue;
            }
            $shared = array_intersect_key($item, array_flip($sharedFields));
            if (! empty($item['id'])) {
                $recurringById[$item['id']] = $shared;
            }
            $recurringByName[$item['name'] ?? ''] = $shared;
        }

        if (! $recurringById && ! $recurringByName) {
            return;
        }

        $futureRows = $user->budgetData()->where('key', $key)->where('period', '>', $period)->get();

        foreach ($futureRows as $row) {
            $rowItems = json_decode($row->value, true);
            if (! is_array($rowItems)) {
                continue;
            }

            $changed = false;
            foreach ($rowItems as &$rowItem) {
                if (! is_array($rowItem)) {
                    continue;
                }

                $shared = null;
                if (! empty($rowItem['id']) && isset($recurringById[$rowItem['id']])) {
                    $shared = $recurringById[$rowItem['id']];
                } elseif (isset($recurringByName[$rowItem['name'] ?? ''])) {
                    $shared = $recurringByName[$rowItem['name']];
                }

                if ($shared) {
                    $rowItem = array_merge($rowItem, $shared);
                    $changed = true;
                }
            }
            unset($rowItem);

            if ($changed) {
                $row->update(['value' => json_encode($rowItems)]);
            }
        }
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

    public function voice(Request $request, GeminiClient $gemini)
    {
        $data = $request->validate([
            'audio' => ['required', 'string', 'max:8000000'],
            'mime_type' => ['required', 'string', 'in:audio/webm,audio/ogg,audio/mp4,audio/wav,audio/mpeg,audio/aac'],
            'expense_categories' => ['required', 'array'],
            'expense_categories.*' => ['string'],
            'savings_categories' => ['required', 'array'],
            'savings_categories.*' => ['string'],
        ]);

        $categories = array_values(array_unique(array_merge($data['expense_categories'], $data['savings_categories'])));
        $categoryList = implode(', ', $categories);
        $lang = $request->cookie('lang', 'sr');

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

        if ($lang === 'en') {
            $prompt = <<<PROMPT
            This is an audio recording of the user speaking in English about their
            finances. Listen to it and transcribe it mentally, then determine
            whether they want to:
            - add an expense (add_expense)
            - add income (add_income)
            - add a savings/asset item (add_saving)
            - or the recording is unclear/unrelated (unclear)

            If clear, extract the item name (name), amount (amount, number only),
            currency (currency: RSD/EUR/USD, default RSD if not stated), and for
            expense/income the frequency (freq: 1=monthly, 2=every 2 months,
            3=every 3 months, 0=one-time).

            Important for freq: a spontaneous mention of a single purchase (coffee,
            taxi, fuel, food, a gift, etc.) is a ONE-TIME expense (freq=0) — that is
            the default. Only use monthly/every-2-months/every-3-months if they
            explicitly say it repeats ("monthly", "every month", "subscription",
            "membership", "installment", "rent") or clearly describe a recurring
            obligation. Don't assume something is monthly just because frequency
            wasn't stated. Savings items don't need freq.

            If add_expense or add_saving, pick the best matching category
            (category) from this list based on the item name: {$categoryList}. If
            none clearly match, use "Ostalo". For add_income or unclear, set
            category to "Ostalo".
            PROMPT;
        } else {
            $prompt = <<<PROMPT
            Ovo je audio snimak na kome korisnik govori na srpskom o svojim
            finansijama. Preslušaj ga, pa prepoznaj da li korisnik želi da:
            - doda trošak (add_expense)
            - doda primanje/prihod (add_income)
            - doda stavku štednje/imovine (add_saving)
            - ili snimak nije jasan/nije o finansijama (unclear)

            Ako je jasno, izvuci naziv stavke (name), iznos (amount, samo broj), valutu
            (currency: RSD/EUR/USD, podrazumevano RSD ako nije rečeno), i za trošak/primanje
            učestalost (freq: 1=mesečno, 2=na 2 meseca, 3=na 3 meseca, 0=jednokratno).

            Važno za freq: spontan pomen pojedinačne kupovine (kafa, taxi, gorivo, hrana,
            poklon i slično) je JEDNOKRATAN trošak (freq=0) — to je podrazumevana vrednost.
            Koristi mesečno/na 2 meseca/na 3 meseca SAMO ako korisnik eksplicitno kaže da se
            ponavlja ("mesečno", "svaki mesec", "na dva meseca", "pretplata", "članarina",
            "rata", "kirija", "stanarina") ili opisuje očigledno ponavljajuću obavezu. Ne
            pretpostavljaj automatski da je nešto mesečno samo zato što učestalost nije
            eksplicitno navedena. Za štednju freq nije potreban.

            Ako je add_expense ili add_saving, izaberi najprikladniju kategoriju (category)
            iz ove liste na osnovu naziva stavke: {$categoryList}. Ako nijedna jasno ne
            odgovara, koristi "Ostalo". Za add_income ili unclear, postavi category na
            "Ostalo".
            PROMPT;
        }

        try {
            $raw = $gemini->generate($prompt, $schema, [
                ['mime_type' => $data['mime_type'], 'data' => $data['audio']],
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

}
