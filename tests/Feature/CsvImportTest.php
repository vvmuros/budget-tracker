<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): UploadedFile
    {
        return new UploadedFile(
            base_path("tests/Fixtures/{$name}"),
            $name,
            'text/csv',
            null,
            true
        );
    }

    public function test_preview_returns_headers_and_sample_rows(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/import/preview', [
            'file' => $this->fixture('monefy_style_export.csv'),
        ]);

        $response->assertOk();
        $response->assertJson([
            'headers' => ['date', 'account', 'category', 'amount', 'currency', 'converted amount', 'converted currency', 'description'],
            'total_rows' => 5,
            'detected_date_column' => 0,
            'detected_date_format' => 'd/m/Y',
        ]);
        $this->assertCount(5, $response->json('sample_rows'));
    }

    public function test_commit_reports_why_rows_were_skipped_when_the_date_format_is_wrong(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/import/commit', [
            'file' => $this->fixture('monefy_style_export.csv'),
            'has_header' => true,
            'date_column' => 0,
            'name_column' => 7,
            'amount_column' => 3,
            'category_column' => 2,
            'date_format' => 'Y-m-d',
            'default_currency' => 'RSD',
            'kind_mode' => 'sign',
        ]);

        $response->assertOk();
        $response->assertJson([
            'imported' => 0,
            'skipped' => 5,
            'skip_reasons' => ['invalid_date' => 5, 'invalid_amount' => 0, 'empty_name' => 0],
        ]);
    }

    public function test_commit_splits_by_sign_into_expense_and_income_across_months(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/import/commit', [
            'file' => $this->fixture('monefy_style_export.csv'),
            'has_header' => true,
            'date_column' => 0,
            'name_column' => 7,
            'amount_column' => 3,
            'currency_column' => 4,
            'category_column' => 2,
            'date_format' => 'd/m/Y',
            'default_currency' => 'RSD',
            'kind_mode' => 'sign',
        ]);

        $response->assertOk();
        $response->assertJson(['imported' => 5, 'skipped' => 0]);

        $julyExpenses = json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-07'])->value('value'),
            true
        );
        $this->assertCount(3, $julyExpenses, 'Three expense rows fall in July 2026');

        $julyIncome = json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'income-items', 'period' => '2026-07'])->value('value'),
            true
        );
        $this->assertCount(1, $julyIncome);
        $this->assertSame('Plata', $julyIncome[0]['name']);
        $this->assertEquals(130000, $julyIncome[0]['amount']);

        $this->assertDatabaseHas('budget_data', ['user_id' => $user->id, 'key' => 'custom-categories-expense', 'period' => 'global']);
        $categories = json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'custom-categories-expense', 'period' => 'global'])->value('value'),
            true
        );
        $this->assertContains('Entertainment', $categories);
    }

    public function test_commit_with_fixed_kind_treats_every_row_as_the_same_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/import/commit', [
            'file' => $this->fixture('generic_bank_export.csv'),
            'has_header' => true,
            'date_column' => 0,
            'name_column' => 1,
            'amount_column' => 2,
            'category_column' => 3,
            'date_format' => 'Y-m-d',
            'default_currency' => 'RSD',
            'kind_mode' => 'fixed',
            'default_kind' => 'expense',
        ]);

        $response->assertOk();
        $response->assertJson(['imported' => 4, 'skipped' => 0]);

        $julyExpenses = json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-07'])->value('value'),
            true
        );
        $juneExpenses = json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-06'])->value('value'),
            true
        );

        $this->assertCount(2, $julyExpenses);
        $this->assertCount(2, $juneExpenses);
    }

    public function test_commit_merges_into_a_month_that_already_has_data_instead_of_overwriting(): void
    {
        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'existing-1', 'name' => 'Kirija', 'amount' => 20000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Stanovanje'],
            ]),
        ]);

        $this->actingAs($user)->postJson('/api/import/commit', [
            'file' => $this->fixture('generic_bank_export.csv'),
            'has_header' => true,
            'date_column' => 0,
            'name_column' => 1,
            'amount_column' => 2,
            'category_column' => 3,
            'date_format' => 'Y-m-d',
            'default_currency' => 'RSD',
            'kind_mode' => 'fixed',
            'default_kind' => 'expense',
        ])->assertOk();

        $julyExpenses = json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-07'])->value('value'),
            true
        );

        $this->assertCount(3, $julyExpenses, 'Existing item plus 2 imported July rows');
        $this->assertTrue(collect($julyExpenses)->contains('name', 'Kirija'));
    }

    public function test_commit_flags_same_name_same_amount_same_day_as_duplicate_but_not_same_name_different_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/import/commit', [
            'file' => $this->fixture('duplicate_test.csv'),
            'has_header' => true,
            'date_column' => 0,
            'name_column' => 1,
            'amount_column' => 2,
            'currency_column' => 3,
            'date_format' => 'Y-m-d',
            'default_currency' => 'RSD',
            'kind_mode' => 'sign',
        ]);

        $response->assertOk();
        // Prodavnica 9200 + Prodavnica 8000 (different amounts, both kept) + ABS centrala once (its repeat is the duplicate).
        $response->assertJson(['imported' => 3, 'skipped' => 1, 'skip_reasons' => ['duplicate' => 1]]);
        $this->assertCount(1, $response->json('duplicates'));
        $this->assertSame('ABS centrala', $response->json('duplicates.0.name'));

        $expenses = json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-07'])->value('value'),
            true
        );
        $this->assertCount(3, $expenses);
        $amounts = collect($expenses)->pluck('amount')->sort()->values()->all();
        $this->assertEquals([8000.0, 9200.0, 35000.0], $amounts);
    }

    public function test_commit_flags_duplicate_against_an_already_imported_item_from_a_previous_commit(): void
    {
        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'existing-1', 'name' => 'Prodavnica', 'amount' => 8000, 'currency' => 'RSD', 'freq' => 0, 'active' => true, 'category' => 'Ostalo', 'createdAt' => (new \DateTime('2026-07-10'))->getTimestamp() * 1000],
            ]),
        ]);

        $response = $this->actingAs($user)->postJson('/api/import/commit', [
            'file' => $this->fixture('duplicate_test.csv'),
            'has_header' => true,
            'date_column' => 0,
            'name_column' => 1,
            'amount_column' => 2,
            'currency_column' => 3,
            'date_format' => 'Y-m-d',
            'default_currency' => 'RSD',
            'kind_mode' => 'sign',
        ]);

        $response->assertOk();
        // Prodavnica 8000 now matches the pre-existing row, so only Prodavnica 9200 + ABS centrala (once) are new.
        $response->assertJson(['imported' => 2, 'skipped' => 2, 'skip_reasons' => ['duplicate' => 2]]);
    }

    public function test_commit_includes_duplicates_when_explicitly_requested(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/import/commit', [
            'file' => $this->fixture('duplicate_test.csv'),
            'has_header' => true,
            'date_column' => 0,
            'name_column' => 1,
            'amount_column' => 2,
            'currency_column' => 3,
            'date_format' => 'Y-m-d',
            'default_currency' => 'RSD',
            'kind_mode' => 'sign',
            'include_duplicates' => true,
        ]);

        $response->assertOk();
        $response->assertJson(['imported' => 4, 'skipped' => 0]);
    }
}
