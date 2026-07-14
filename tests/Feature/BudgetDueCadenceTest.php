<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetDueCadenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_two_months_item_only_counts_on_its_due_months(): void
    {
        $this->travelTo('2026-09-15');

        $user = User::factory()->create();

        // Paid in July, every 2 months: due July, September, November...
        // Not due in August or October.
        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'graza-1', 'name' => 'Graza', 'amount' => 100, 'currency' => 'RSD', 'freq' => 2, 'active' => true, 'dueAnchor' => '2026-07', 'category' => 'Ostalo'],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');
        $months = collect($response->json('months'))->keyBy('period');

        $this->assertEquals(100, $months['2026-07']['expense'], 'Due in July (the anchor month)');
        $this->assertEquals(0, $months['2026-08']['expense'], 'Not due in August');
        $this->assertEquals(100, $months['2026-09']['expense'], 'Due again in September (2 months later)');
    }

    public function test_custom_interval_item_without_anchor_counts_every_month_for_backward_compatibility(): void
    {
        $this->travelTo('2026-09-15');

        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'legacy-1', 'name' => 'Old item', 'amount' => 50, 'currency' => 'RSD', 'freq' => 3, 'active' => true, 'category' => 'Ostalo'],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/budget/yearly');
        $months = collect($response->json('months'))->keyBy('period');

        $this->assertEquals(50, $months['2026-08']['expense'], 'No anchor set yet — keeps counting every month like before');
        $this->assertEquals(50, $months['2026-09']['expense']);
    }
}
