<?php

namespace App\Http\Controllers;

use App\Services\BudgetCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private BudgetCalculator $calc)
    {
    }

    public function monthlyPdf(Request $request)
    {
        $period = $request->query('period', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $period) === 1, 422, 'Nevažeći period.');

        $user = $request->user();
        $lang = $request->cookie('lang', 'sr');

        $income = $this->calc->resolveEffectiveValue($user, 'income-items', $period);
        $expense = $this->calc->resolveEffectiveValue($user, 'expense-items', $period);
        $rates = $this->calc->resolveEffectiveValue($user, 'expense-rates', $period);
        $savingsJson = $user->budgetData()->where('key', 'savings-items')->where('period', 'global')->value('value');

        $ratesArr = json_decode($rates['value'] ?? '{}', true) ?: ['usd' => 0, 'eur' => 0];

        $incomeValue = $income && ! $income['is_exact'] ? $this->calc->stripOneTimeItems($income['value']) : ($income['value'] ?? '[]');
        $expenseValue = $expense && ! $expense['is_exact'] ? $this->calc->stripOneTimeItems($expense['value']) : ($expense['value'] ?? '[]');

        $incomeItems = collect(json_decode($incomeValue, true) ?: [])
            ->filter(fn ($it) => $this->calc->isItemActive($it, $period));

        $expenseItems = collect(json_decode($expenseValue, true) ?: [])
            ->filter(fn ($it) => $this->calc->isItemActive($it, $period) && empty($it['paidFromSavings']));

        $savingsItems = collect(json_decode($savingsJson ?? '[]', true) ?: []);

        $incomeTotal = $this->calc->sumActiveItems($incomeValue, $ratesArr, $period);
        $expenseTotal = $this->calc->sumActiveItems($expenseValue, $ratesArr, $period);
        $savingsTotal = $savingsItems->sum(fn ($it) => $this->calc->toRsd($it['amount'] ?? 0, $it['currency'] ?? 'RSD', $ratesArr));

        $categories = collect($this->calc->sumByCategory($expenseValue, $ratesArr, $period))
            ->sortDesc();
        $categoryMax = $categories->max() ?: 1;

        $pdf = Pdf::loadView('reports.monthly', [
            'period' => $period,
            'lang' => $lang,
            'incomeItems' => $incomeItems->values(),
            'expenseItems' => $expenseItems->values(),
            'savingsItems' => $savingsItems->values(),
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netTotal' => $incomeTotal - $expenseTotal,
            'savingsTotal' => $savingsTotal,
            'categories' => $categories,
            'categoryMax' => $categoryMax,
            'rates' => $ratesArr,
        ])->setPaper('a4');

        return $pdf->download("bilanso-izvestaj-{$period}.pdf");
    }
}
