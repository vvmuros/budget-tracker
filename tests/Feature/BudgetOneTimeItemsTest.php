<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetOneTimeItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_time_income_does_not_carry_forward_into_a_later_month(): void
    {
        $this->travelTo('2026-08-15');

        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'income-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'salary-1', 'name' => 'Plata', 'amount' => 130000, 'currency' => 'RSD', 'freq' => 1, 'active' => true],
                ['id' => 'gift-1', 'name' => 'Gift', 'amount' => 100, 'currency' => 'EUR', 'freq' => 0, 'active' => true],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget?period=2026-08');
        $response->assertOk();
        $income = json_decode($response->json('data.income-items'), true);

        $this->assertCount(1, $income, 'Only the recurring salary should carry forward');
        $this->assertSame('Plata', $income[0]['name']);
    }

    public function test_one_time_expense_does_not_count_toward_a_later_months_yearly_total(): void
    {
        $this->travelTo('2026-08-15');

        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'rent-1', 'name' => 'Kirija', 'amount' => 20000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Stanovanje'],
                ['id' => 'trip-1', 'name' => 'Putovanje', 'amount' => 50000, 'currency' => 'RSD', 'freq' => 0, 'active' => true, 'category' => 'Ostalo'],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');
        $months = collect($response->json('months'))->keyBy('period');

        $this->assertEquals(70000, $months['2026-07']['expense'], 'July itself keeps both items');
        $this->assertEquals(20000, $months['2026-08']['expense'], 'August only inherits the recurring rent');
    }
}
