<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetMirrorRecurringTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_recurring_item_mirrors_forward_but_keeps_each_months_active_flag(): void
    {
        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-06',
            'value' => json_encode([
                ['id' => 'loan-1', 'name' => 'Rata', 'amount' => 5000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Otplate', 'endPeriod' => null],
            ]),
        ]);

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'loan-1', 'name' => 'Rata', 'amount' => 5000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Otplate', 'endPeriod' => null],
            ]),
        ]);

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-08',
            'value' => json_encode([
                ['id' => 'loan-1', 'name' => 'Rata', 'amount' => 5000, 'currency' => 'RSD', 'freq' => 1, 'active' => false, 'category' => 'Otplate', 'endPeriod' => null],
            ]),
        ]);

        $this->actingAs($user)->postJson('/api/budget', [
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'loan-1', 'name' => 'Rata', 'amount' => 7200, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Otplate', 'endPeriod' => '2026-09'],
            ]),
        ])->assertOk();

        $august = json_decode(
            $this->actingAs($user)->getJson('/api/budget?period=2026-08')->json('data.expense-items'),
            true
        );
        $june = json_decode(
            $this->actingAs($user)->getJson('/api/budget?period=2026-06')->json('data.expense-items'),
            true
        );

        $this->assertSame(7200, $august[0]['amount'], 'August should pick up the new amount');
        $this->assertSame('2026-09', $august[0]['endPeriod'], 'August should pick up the new end month');
        $this->assertFalse($august[0]['active'], 'August keeps its own active flag, not mirrored');

        $this->assertSame(5000, $june[0]['amount'], 'Past months must not be rewritten');
        $this->assertNull($june[0]['endPeriod']);
    }

    public function test_one_time_items_do_not_mirror_forward(): void
    {
        $user = User::factory()->create();

        BudgetData::create([
            'user_id' => $user->id,
            'key' => 'expense-items',
            'period' => '2026-08',
            'value' => json_encode([
                ['id' => 'trip-1', 'name' => 'Putovanje', 'amount' => 3000, 'currency' => 'RSD', 'freq' => 0, 'active' => true, 'category' => 'Ostalo', 'endPeriod' => null],
            ]),
        ]);

        $this->actingAs($user)->postJson('/api/budget', [
            'key' => 'expense-items',
            'period' => '2026-07',
            'value' => json_encode([
                ['id' => 'trip-1', 'name' => 'Putovanje', 'amount' => 9999, 'currency' => 'RSD', 'freq' => 0, 'active' => true, 'category' => 'Ostalo', 'endPeriod' => null],
            ]),
        ])->assertOk();

        $august = json_decode(
            $this->actingAs($user)->getJson('/api/budget?period=2026-08')->json('data.expense-items'),
            true
        );

        $this->assertSame(3000, $august[0]['amount'], 'One-time items must stay independent per month');
    }
}
