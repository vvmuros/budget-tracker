<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_renaming_a_custom_category_cascades_to_the_list_usage_and_every_saved_month(): void
    {
        $user = User::factory()->create();

        BudgetData::create(['user_id' => $user->id, 'key' => 'custom-categories-expense', 'period' => 'global', 'value' => json_encode(['Ljubimci'])]);
        BudgetData::create(['user_id' => $user->id, 'key' => 'category-usage-expense', 'period' => 'global', 'value' => json_encode(['Ljubimci' => 2])]);
        BudgetData::create(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-06', 'value' => json_encode([
            ['id' => 'e1', 'name' => 'Hrana za psa', 'amount' => 2000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Ljubimci'],
        ])]);
        BudgetData::create(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-07', 'value' => json_encode([
            ['id' => 'e1', 'name' => 'Hrana za psa', 'amount' => 2000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Ljubimci'],
        ])]);

        $this->actingAs($user)->postJson('/api/budget/categories', [
            'kind' => 'expense',
            'action' => 'rename',
            'from' => 'Ljubimci',
            'to' => 'Kućni ljubimci',
        ])->assertOk();

        $this->assertSame(['Kućni ljubimci'], json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'custom-categories-expense'])->value('value'), true
        ));
        $this->assertSame(['Kućni ljubimci' => 2], json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'category-usage-expense'])->value('value'), true
        ));

        foreach (['2026-06', '2026-07'] as $period) {
            $items = json_decode(BudgetData::where(['user_id' => $user->id, 'key' => 'expense-items', 'period' => $period])->value('value'), true);
            $this->assertSame('Kućni ljubimci', $items[0]['category']);
        }
    }

    public function test_deleting_a_custom_category_falls_items_back_to_ostalo(): void
    {
        $user = User::factory()->create();

        BudgetData::create(['user_id' => $user->id, 'key' => 'custom-categories-expense', 'period' => 'global', 'value' => json_encode(['Ljubimci'])]);
        BudgetData::create(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-07', 'value' => json_encode([
            ['id' => 'e1', 'name' => 'Hrana za psa', 'amount' => 2000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Ljubimci'],
        ])]);

        $this->actingAs($user)->postJson('/api/budget/categories', [
            'kind' => 'expense',
            'action' => 'delete',
            'from' => 'Ljubimci',
        ])->assertOk();

        $this->assertSame([], json_decode(
            BudgetData::where(['user_id' => $user->id, 'key' => 'custom-categories-expense'])->value('value'), true
        ));

        $items = json_decode(BudgetData::where(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-07'])->value('value'), true);
        $this->assertSame('Ostalo', $items[0]['category']);
    }
}
