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
        $sampleRows = array_slice($rows, 0, 5);

        [$detectedColumn, $detectedFormat] = $this->detectDateColumn($headers, $sampleRows);

        return response()->json([
            'headers' => $headers,
            'sample_rows' => $sampleRows,
            'total_rows' => count($rows),
            'detected_date_column' => $detectedColumn,
            'detected_date_format' => $detectedFormat,
        ]);
    }

    /**
     * Guesses which column holds the date and which of the supported formats
     * it's in, by trying to parse every sample value in each column against
     * each format — a column only counts as a match if ALL sample values
     * parse successfully under that one format. Avoids the "picked the wrong
     * format, everything silently gets skipped" trap of leaving this to the
     * user's memory.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function detectDateColumn(array $headers, array $sampleRows): array
    {
        if (! $sampleRows) {
            return [null, null];
        }

        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y'];

        foreach (array_keys($headers) as $colIndex) {
            foreach ($formats as $format) {
                $allMatch = true;
                foreach ($sampleRows as $row) {
                    $value = trim($row[$colIndex] ?? '');
                    $parsed = $value !== '' ? \DateTime::createFromFormat('!'.$format, $value) : false;
                    if (! $parsed) {
                        $allMatch = false;
                        break;
                    }
                }
                if ($allMatch) {
                    return [$colIndex, $format];
                }
            }
        }

        return [null, null];
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
            'include_duplicates' => ['nullable', 'boolean'],
        ]);

        $includeDuplicates = (bool) ($data['include_duplicates'] ?? false);

        $rows = $this->readCsv($request->file('file'));
        if ($data['has_header']) {
            array_shift($rows);
        }
        $rows = array_slice($rows, 0, self::MAX_ROWS);

        $user = $request->user();
        $candidates = [];
        $newCategories = [];
        $skipped = 0;
        $skipReasons = ['invalid_date' => 0, 'invalid_amount' => 0, 'empty_name' => 0, 'duplicate' => 0];

        foreach ($rows as $row) {
            $dateRaw = trim($row[$data['date_column']] ?? '');
            $name = trim($row[$data['name_column']] ?? '');
            $amountRaw = trim($row[$data['amount_column']] ?? '');

            $date = \DateTime::createFromFormat('!'.$data['date_format'], $dateRaw);
            $cleanedAmount = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $amountRaw));
            $amount = is_numeric($cleanedAmount) ? (float) $cleanedAmount : 0.0;

            if (! $date || $amount === 0.0 || $name === '') {
                $skipped++;
                if (! $date) {
                    $skipReasons['invalid_date']++;
                } elseif ($amount === 0.0) {
                    $skipReasons['invalid_amount']++;
                } else {
                    $skipReasons['empty_name']++;
                }
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

            $category = null;
            if ($kind === 'expense') {
                $category = 'Ostalo';
                if (isset($data['category_column']) && ! empty($row[$data['category_column']])) {
                    $category = trim($row[$data['category_column']]);
                }
            }

            $candidates[$period.'|'.$kind][] = [
                'date' => $date,
                'name' => $name,
                'amount' => round(abs($amount), 2),
                'currency' => $currency,
                'category' => $category,
            ];
        }

        $imported = 0;
        $duplicates = [];
        $months = [];

        foreach ($candidates as $key => $rowsForBucket) {
            [$period, $kind] = explode('|', $key);
            $budgetKey = $kind === 'expense' ? 'expense-items' : 'income-items';

            $row = $user->budgetData()->where('key', $budgetKey)->where('period', $period)->first();
            $existing = $row ? (json_decode($row->value, true) ?: []) : [];

            $seenInBatch = [];
            $newItems = [];

            foreach ($rowsForBucket as $candidate) {
                $dayKey = $candidate['date']->format('Y-m-d');
                $dedupeKey = Str::lower($candidate['name']).'|'.$candidate['amount'].'|'.$candidate['currency'].'|'.$dayKey;

                $isDuplicate = isset($seenInBatch[$dedupeKey])
                    || $this->matchesExistingItem($existing, $candidate, $dayKey);

                if ($isDuplicate && ! $includeDuplicates) {
                    $skipped++;
                    $skipReasons['duplicate']++;
                    $duplicates[] = [
                        'name' => $candidate['name'],
                        'amount' => $candidate['amount'],
                        'currency' => $candidate['currency'],
                        'date' => $dayKey,
                    ];
                    continue;
                }

                $seenInBatch[$dedupeKey] = true;

                $item = [
                    'id' => 'imp_'.Str::random(10),
                    'name' => $candidate['name'],
                    'amount' => $candidate['amount'],
                    'currency' => $candidate['currency'],
                    'freq' => 0,
                    'active' => true,
                    'endPeriod' => null,
                    'createdAt' => $candidate['date']->getTimestamp() * 1000,
                ];

                if ($kind === 'expense') {
                    $item['category'] = $candidate['category'];
                    if ($candidate['category']) {
                        $newCategories[$candidate['category']] = true;
                    }
                }

                $newItems[] = $item;
                $imported++;
            }

            if ($newItems) {
                BudgetData::updateOrCreate(
                    ['user_id' => $user->id, 'key' => $budgetKey, 'period' => $period],
                    ['value' => json_encode(array_merge($existing, $newItems))]
                );
                $months[$period] = true;
            }
        }

        if ($newCategories) {
            $this->registerNewExpenseCategories($user, array_keys($newCategories));
        }

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'skip_reasons' => $skipReasons,
            'duplicates' => $duplicates,
            'months' => array_values(array_keys($months)),
        ]);
    }

    /**
     * A row only counts as a duplicate of something already saved if name +
     * amount + currency match AND we can confirm it's the same day — existing
     * items without a createdAt (e.g. entered manually, no date recorded)
     * are never flagged, since there's nothing to compare the day against.
     */
    private function matchesExistingItem(array $existing, array $candidate, string $dayKey): bool
    {
        foreach ($existing as $existingItem) {
            if (empty($existingItem['createdAt'])) {
                continue;
            }

            $existingDay = (new \DateTime())->setTimestamp((int) ($existingItem['createdAt'] / 1000))->format('Y-m-d');
            if ($existingDay !== $dayKey) {
                continue;
            }

            if (Str::lower(trim($existingItem['name'] ?? '')) !== Str::lower($candidate['name'])) {
                continue;
            }

            if (abs((float) ($existingItem['amount'] ?? 0) - $candidate['amount']) > 0.005) {
                continue;
            }

            if (($existingItem['currency'] ?? null) !== $candidate['currency']) {
                continue;
            }

            return true;
        }

        return false;
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
