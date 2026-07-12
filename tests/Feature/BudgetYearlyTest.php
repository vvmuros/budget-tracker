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
                ['name' => 'Kafa', 'amount' => 500, 'currency' => 'RSD', 'freq' => 0, 'active' => true],
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
}
