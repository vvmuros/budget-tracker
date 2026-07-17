<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetSavingsDivertedTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_diverted_to_savings_reduces_the_months_income_total(): void
    {
        $this->travelTo('2026-07-15');

        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'income-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'salary-1', 'name' => 'Plata', 'amount' => 130000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'savingsDiverted' => 40000],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');
        $months = collect($response->json('months'))->keyBy('period');

        $this->assertEquals(90000, $months['2026-07']['income']);
    }

    public function test_diverted_amount_larger_than_the_income_itself_floors_at_zero_not_negative(): void
    {
        $this->travelTo('2026-07-15');

        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'income-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'gift-1', 'name' => 'Gift', 'amount' => 100, 'currency' => 'EUR', 'freq' => 0, 'active' => true, 'savingsDiverted' => 400],
            ]),
        ]);
        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-rates',
            'period' => '2026-07',
            'value' => json_encode(['usd' => 100, 'eur' => 100]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');
        $months = collect($response->json('months'))->keyBy('period');

        // 100 EUR income with 400 EUR "diverted" should floor at 0, not go to -300 EUR worth.
        $this->assertEquals(0, $months['2026-07']['income']);
    }
}
