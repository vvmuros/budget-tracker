<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetPaidFromSavingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expense_paid_from_savings_does_not_count_toward_the_monthly_total(): void
    {
        $this->travelTo('2026-07-15');

        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'rent-1', 'name' => 'Kirija', 'amount' => 20000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Stanovanje'],
                ['id' => 'graza-1', 'name' => 'Graza', 'amount' => 100, 'currency' => 'EUR', 'freq' => 2, 'dueAnchor' => '2026-07', 'active' => true, 'category' => 'Otplate', 'paidFromSavings' => ['savingsId' => 's1', 'amount' => 100, 'currency' => 'EUR']],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');
        $months = collect($response->json('months'))->keyBy('period');

        $this->assertEquals(20000, $months['2026-07']['expense'], 'The savings-funded item should be excluded from the monthly expense total');
    }
}
