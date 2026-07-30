<?php

namespace App\Http\Controllers;

use App\Models\BudgetData;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Column-mapped CSV import — deliberately format-agnostic (no bespoke parser
 * per app) since export formats vary and change between apps/versions. The
 * user maps whichever columns their file has to date/name/amount/etc.
 */
class ImportController extends Controller
{
    private const MAX_ROWS = 2000;

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $rows = $this->readCsv($request->file('file'));
        if (empty($rows)) {
            return response()->json(['error' => 'Empty file.'], 422);
        }

        $headers = array_shift($rows);

        return response()->json([
            'headers' => $headers,
            'sample_rows' => array_slice($rows, 0, 5),
            'total_rows' => count($rows),
        ]);
    }

    public function commit(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'has_header' => ['required', 'boolean'],
            'date_column' => ['required', 'integer', 'min:0'],
            'name_column' => ['required', 'integer', 'min:0'],
            'amount_column' => ['required', 'integer', 'min:0'],
            'currency_column' => ['nullable', 'integer', 'min:0'],
            'category_column' => ['nullable', 'integer', 'min:0'],
            'date_format' => ['required', 'string', 'in:Y-m-d,d/m/Y,m/d/Y,d.m.Y'],
            'default_currency' => ['required', 'string', 'in:RSD,EUR,USD'],
            'kind_mode' => ['required', 'string', 'in:sign,fixed'],
            'default_kind' => ['required_if:kind_mode,fixed', 'nullable', 'string', 'in:expense,income'],
        ]);

        $rows = $this->readCsv($request->file('file'));
        if ($data['has_header']) {
            array_shift($rows);
        }
        $rows = array_slice($rows, 0, self::MAX_ROWS);

        $user = $request->user();
        $buckets = [];
        $newCategories = [];
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $dateRaw = trim($row[$data['date_column']] ?? '');
            $name = trim($row[$data['name_column']] ?? '');
            $amountRaw = trim($row[$data['amount_column']] ?? '');

            $date = \DateTime::createFromFormat('!'.$data['date_format'], $dateRaw);
            $cleanedAmount = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $amountRaw));
            $amount = is_numeric($cleanedAmount) ? (float) $cleanedAmount : 0.0;

            if (! $date || $amount === 0.0 || $name === '') {
                $skipped++;
                continue;
            }

            $period = $date->format('Y-m');
            $kind = $data['kind_mode'] === 'sign' ? ($amount < 0 ? 'expense' : 'income') : $data['default_kind'];

            $currency = $data['default_currency'];
            if (isset($data['currency_column']) && ! empty($row[$data['currency_column']])) {
                $candidate = strtoupper(trim($row[$data['currency_column']]));
                if (in_array($candidate, ['RSD', 'EUR', 'USD'], true)) {
                    $currency = $candidate;
                }
            }

            $item = [
                'id' => 'imp_'.Str::random(10),
                'name' => $name,
                'amount' => round(abs($amount), 2),
                'currency' => $currency,
                'freq' => 0,
                'active' => true,
                'endPeriod' => null,
            ];

            if ($kind === 'expense') {
                $category = 'Ostalo';
                if (isset($data['category_column']) && ! empty($row[$data['category_column']])) {
                    $category = trim($row[$data['category_column']]);
                    $newCategories[$category] = true;
                }
                $item['category'] = $category;
            }

            $buckets[$period.'|'.$kind][] = $item;
            $imported++;
        }

        foreach ($buckets as $key => $newItems) {
            [$period, $kind] = explode('|', $key);
            $budgetKey = $kind === 'expense' ? 'expense-items' : 'income-items';

            $row = $user->budgetData()->where('key', $budgetKey)->where('period', $period)->first();
            $existing = $row ? (json_decode($row->value, true) ?: []) : [];

            BudgetData::updateOrCreate(
                ['user_id' => $user->id, 'key' => $budgetKey, 'period' => $period],
                ['value' => json_encode(array_merge($existing, $newItems))]
            );
        }

        if ($newCategories) {
            $this->registerNewExpenseCategories($user, array_keys($newCategories));
        }

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'months' => array_values(array_unique(array_map(fn ($k) => explode('|', $k)[0], array_keys($buckets)))),
        ]);
    }

    /**
     * So imported category names show up as real, pickable custom categories
     * afterward instead of an orphaned string the dropdown doesn't recognize.
     */
    private function registerNewExpenseCategories($user, array $categories): void
    {
        $defaults = ['Stanovanje', 'Hrana', 'Prevoz', 'Zdravlje', 'Zabava', 'Računi', 'Otplate', 'Ostalo'];

        $row = $user->budgetData()->where('key', 'custom-categories-expense')->where('period', 'global')->first();
        $existing = $row ? (json_decode($row->value, true) ?: []) : [];

        $toAdd = array_diff($categories, $defaults, $existing);
        if (! $toAdd) {
            return;
        }

        BudgetData::updateOrCreate(
            ['user_id' => $user->id, 'key' => 'custom-categories-expense', 'period' => 'global'],
            ['value' => json_encode(array_values(array_unique([...$existing, ...$toAdd])))]
        );
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
