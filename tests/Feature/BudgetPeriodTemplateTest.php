<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetPeriodTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_carries_forward_independently_even_if_a_later_month_only_saved_an_expense(): void
    {
        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'income-items',
            'period' => '2026-05',
            'value' => json_encode([
                ['name' => 'Plata', 'amount' => 80000, 'currency' => 'RSD', 'freq' => 1, 'active' => true],
            ]),
        ]);

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-06',
            'value' => json_encode([
                ['name' => 'Kafa', 'amount' => 500, 'currency' => 'RSD', 'freq' => 1, 'active' => true],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget?period=2026-07');

        $response->assertOk();
        $income = json_decode($response->json('data.income-items'), true);

        $this->assertNotNull($income, 'income-items should not be missing/null for 2026-07');
        $this->assertSame(80000, $income[0]['amount']);
    }
}
