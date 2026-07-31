<?php

namespace App\Http\Controllers;

use App\Models\BudgetData;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Column-mapped CSV/Excel import — deliberately format-agnostic (no bespoke
 * parser per app) since export formats vary and change between apps/
 * versions. The user maps whichever columns their file has to date/name/
 * amount/etc.
 */
class ImportController extends Controller
{
    private const MAX_ROWS = 2000;

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xls,xlsx'],
            'skip_rows' => ['nullable', 'integer', 'min:0'],
        ]);

        $allRows = $this->readRows($request->file('file'));
        if (empty($allRows)) {
            return response()->json(['error' => 'Empty file.'], 422);
        }

        $rawPreviewRows = array_slice($allRows, 0, 20);

        $skipRows = $request->filled('skip_rows')
            ? (int) $request->input('skip_rows')
            : $this->detectDataStart($allRows);

        $rows = array_slice($allRows, $skipRows);
        if (empty($rows)) {
            return response()->json(['error' => 'skip_rows leaves no data.'], 422);
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
            'skip_rows' => $skipRows,
            'raw_preview_rows' => $rawPreviewRows,
        ]);
    }

    /**
     * Real bank/wallet exports often have a few rows of branding, account
     * number, and date-range metadata before the actual table starts (seen
     * first-hand with a Banka Intesa .xls export) — plain "first row is the
     * header" parsing misreads that preamble as the header/data entirely.
     * This looks for the smallest number of rows to skip such that the
     * following rows have a column that parses as a real date consistently —
     * that's a strong signal the real transaction table has begun. Falls
     * back to 0 (today's existing behavior) if nothing better is found.
     */
    private function detectDataStart(array $rows): int
    {
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y'];
        $maxSkip = min(30, count($rows) - 1);

        for ($skip = 0; $skip <= $maxSkip; $skip++) {
            $sample = array_slice($rows, $skip + 1, 5);
            if (count($sample) < 3) {
                continue;
            }

            $numCols = count($rows[$skip] ?? []);
            for ($col = 0; $col < $numCols; $col++) {
                foreach ($formats as $format) {
                    $allMatch = true;
                    foreach ($sample as $row) {
                        $value = trim($row[$col] ?? '');
                        if ($value === '' || ! \DateTime::createFromFormat('!'.$format, $value)) {
                            $allMatch = false;
                            break;
                        }
                    }
                    if ($allMatch) {
                        return $skip;
                    }
                }
            }
        }

        return 0;
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
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xls,xlsx'],
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
            'skip_rows' => ['nullable', 'integer', 'min:0'],
        ]);

        $includeDuplicates = (bool) ($data['include_duplicates'] ?? false);

        $rows = $this->readRows($request->file('file'));
        $rows = array_slice($rows, (int) ($data['skip_rows'] ?? 0));
        if ($data['has_header']) {
            array_shift($rows);
        }
        $rows = array_slice($rows, 0, self::MAX_ROWS);

        $dateFormat = $this->resolveDateFormat($rows, $data['date_column'], $data['date_format']);
        $dateFormatAdjusted = $dateFormat !== $data['date_format'];

        $user = $request->user();
        $candidates = [];
        $newCategories = [];
        $skipped = 0;
        $skipReasons = ['invalid_date' => 0, 'invalid_amount' => 0, 'empty_name' => 0, 'duplicate' => 0];

        foreach ($rows as $row) {
            $dateRaw = trim($row[$data['date_column']] ?? '');
            $name = trim($row[$data['name_column']] ?? '');
            $amountRaw = trim($row[$data['amount_column']] ?? '');

            $date = \DateTime::createFromFormat('!'.$dateFormat, $dateRaw);
            $amount = $this->parseAmount($amountRaw) ?? 0.0;

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
            'used_date_format' => $dateFormat,
            'date_format_adjusted' => $dateFormatAdjusted,
        ]);
    }

    /**
     * The dropdown value is just a starting guess (and easy to leave stale
     * across separate uploads, which is exactly what caused a real duplicate
     * mix-up during testing) — if it fails to parse the actual data in the
     * chosen column but a different supported format parses every row
     * cleanly, that one wins instead. Only relevant when the whole column
     * unambiguously fits one format; otherwise the requested one is kept.
     */
    private function resolveDateFormat(array $rows, int $dateColumn, string $requestedFormat): string
    {
        if ($this->allRowsParseWithFormat($rows, $dateColumn, $requestedFormat)) {
            return $requestedFormat;
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y'] as $format) {
            if ($format !== $requestedFormat && $this->allRowsParseWithFormat($rows, $dateColumn, $format)) {
                return $format;
            }
        }

        return $requestedFormat;
    }

    /**
     * "1.448,94" (dot = thousands, comma = decimal — Serbian/most-of-Europe
     * banks, including the Banca Intesa export this was built against) and
     * "1,448.94" (comma = thousands, dot = decimal — US-style) both show up
     * in the wild, plus plenty of exports with no thousands separator at
     * all. Blindly stripping commas (the old behavior) silently mangled
     * amounts like "129,99" into 12999 instead of skipping or erroring —
     * wrong by 100x with no signal anything went wrong. Whichever separator
     * is a currency's decimal point comes last and is followed by 1-2
     * digits (no real currency has 3 decimal places); a separator followed
     * by exactly 3 digits is a thousands grouping and gets dropped instead.
     */
    private function parseAmount(string $raw): ?float
    {
        $value = preg_replace('/[^0-9,.\-]/', '', $raw);
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $value = str_replace(',', '.', str_replace('.', '', $value));
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($lastComma !== false) {
            $value = strlen($value) - $lastComma - 1 === 3
                ? str_replace(',', '', $value)
                : str_replace(',', '.', $value);
        } elseif ($lastDot !== false && strlen($value) - $lastDot - 1 === 3) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function allRowsParseWithFormat(array $rows, int $dateColumn, string $format): bool
    {
        $sawAny = false;
        foreach ($rows as $row) {
            $value = trim($row[$dateColumn] ?? '');
            if ($value === '') {
                continue;
            }
            $sawAny = true;
            if (! \DateTime::createFromFormat('!'.$format, $value)) {
                return false;
            }
        }

        return $sawAny;
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
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($extension, ['xls', 'xlsx'], true)
            ? $this->readExcel($file)
            : $this->readCsv($file);
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

    /**
     * Bank/wallet-app spreadsheet exports (.xls/.xlsx) get normalized to the
     * same plain string rows the CSV path produces, so every date/amount/
     * name parsing rule downstream works identically regardless of source
     * format. Cells that are genuinely typed as dates (not just text that
     * looks like one) are converted straight to Y-m-d — Excel stores those
     * as a serial number, not the text the user sees, so the normal
     * "guess the date format from the string" logic doesn't apply to them.
     *
     * @return array<int, array<int, string>>
     */
    private function readExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                if ($cell->getValue() !== null && ExcelDate::isDateTime($cell)) {
                    $cells[] = ExcelDate::excelToDateTimeObject($cell->getValue())->format('Y-m-d');
                } else {
                    $cells[] = trim((string) $cell->getFormattedValue());
                }
            }

            $rows[] = $cells;
        }

        return $rows;
    }
}
