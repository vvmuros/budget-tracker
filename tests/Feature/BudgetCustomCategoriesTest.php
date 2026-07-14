<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCustomCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_categories_and_usage_counts_are_stored_globally_and_returned_for_any_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/budget', [
            'key' => 'custom-categories-expense',
            'value' => json_encode(['Ljubimci']),
            'period' => '2026-07',
        ])->assertOk();

        $this->actingAs($user)->postJson('/api/budget', [
            'key' => 'category-usage-expense',
            'value' => json_encode(['Ljubimci' => 3, 'Hrana' => 1]),
        ])->assertOk();

        $this->assertDatabaseHas('budget_data', [
            'user_id' => $user->id,
            'key' => 'custom-categories-expense',
            'period' => 'global',
        ]);

        foreach (['2026-07', '2026-08'] as $period) {
            $response = $this->actingAs($user)->getJson("/api/budget?period={$period}");
            $response->assertOk();
            $this->assertSame(['Ljubimci'], json_decode($response->json('data.custom-categories-expense'), true));
            $this->assertSame(['Ljubimci' => 3, 'Hrana' => 1], json_decode($response->json('data.category-usage-expense'), true));
        }
    }
}
