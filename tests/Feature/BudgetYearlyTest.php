<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetYearlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_yearly_summary_starts_at_the_first_saved_month_and_stops_at_the_current_month(): void
    {
        $this->travelTo('2026-08-15');

        $user = User::factory()->create();

        // User only started using the app in June 2026 (this year) — no data before that.
        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'income-items',
            'period' => '2026-06',
            'value' => json_encode([
                ['name' => 'Plata', 'amount' => 80000, 'currency' => 'RSD', 'freq' => 1, 'active' => true],
            ]),
        ]);
        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-06',
            'value' => json_encode([
                ['name' => 'Pretplata', 'amount' => 500, 'currency' => 'RSD', 'freq' => 1, 'active' => true],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');

        $response->assertOk();
        $months = $response->json('months');

        $this->assertCount(3, $months, 'Should cover June, July, August only');
        $this->assertSame('2026-06', $months[0]['period']);
        $this->assertSame('2026-07', $months[1]['period']);
        $this->assertSame('2026-08', $months[2]['period']);

        // June has explicit data; July/August carry it forward as a template.
        $this->assertEquals(80000, $months[0]['income']);
        $this->assertEquals(500, $months[0]['expense']);
        $this->assertEquals(80000, $months[2]['income']);
        $this->assertEquals(500, $months[2]['expense']);
    }

    public function test_yearly_summary_is_empty_for_a_brand_new_account(): void
    {
        $this->travelTo('2026-08-15');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');

        $response->assertOk();
        $this->assertSame([], $response->json('months'));
    }

    public function test_foreign_currency_items_use_sensible_default_rates_when_none_were_ever_saved(): void
    {
        $this->travelTo('2026-07-15');

        $user = User::factory()->create();

        // No expense-rates row at all for this user — should fall back to the
        // app's real default rates, not price EUR/USD items at 0 RSD.
        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['name' => 'Graza', 'amount' => 100, 'currency' => 'EUR', 'freq' => 1, 'active' => true],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');
        $months = collect($response->json('months'))->keyBy('period');

        $this->assertGreaterThan(0, $months['2026-07']['expense'], 'A 100 EUR expense should not price at 0 RSD');
    }

    public function test_expense_with_an_end_month_stops_counting_after_it_passes(): void
    {
        $this->travelTo('2026-09-15');

        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'income-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['name' => 'Plata', 'amount' => 80000, 'currency' => 'RSD', 'freq' => 1, 'active' => true],
            ]),
        ]);
        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['name' => 'Kredit', 'amount' => 5000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'endPeriod' => '2026-08'],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');
        $months = collect($response->json('months'))->keyBy('period');

        $this->assertEquals(5000, $months['2026-07']['expense'], 'Loan is within its end month');
        $this->assertEquals(5000, $months['2026-08']['expense'], 'Loan ends in August, should still count in August');
        $this->assertEquals(0, $months['2026-09']['expense'], 'Loan ended in August, should not count in September');
    }
}
