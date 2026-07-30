<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetChatPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGemini(array $response): void
    {
        $this->mock(GeminiClient::class, function ($mock) use ($response) {
            $mock->shouldReceive('generate')->andReturn(json_encode($response));
        });
    }

    public function test_chat_passes_through_a_valid_period_from_gemini(): void
    {
        $this->travelTo('2026-07-15');
        $user = User::factory()->create();

        $this->fakeGemini([
            'action' => 'add_expense', 'name' => 'Kafa', 'amount' => 500,
            'currency' => 'RSD', 'freq' => 0, 'category' => 'Ostalo', 'period' => '2026-06',
        ]);

        $response = $this->actingAs($user)->postJson('/api/budget/chat', [
            'message' => 'prošlog meseca sam potrošio 500 na kafu',
            'expense_categories' => ['Ostalo'],
            'savings_categories' => ['Ostalo'],
        ]);

        $response->assertOk()->assertJson(['period' => '2026-06']);
    }

    public function test_chat_defaults_to_current_period_when_gemini_omits_it(): void
    {
        $this->travelTo('2026-07-15');
        $user = User::factory()->create();

        $this->fakeGemini([
            'action' => 'add_expense', 'name' => 'Kafa', 'amount' => 500,
            'currency' => 'RSD', 'freq' => 0, 'category' => 'Ostalo',
        ]);

        $response = $this->actingAs($user)->postJson('/api/budget/chat', [
            'message' => 'potrošio sam 500 na kafu',
            'expense_categories' => ['Ostalo'],
            'savings_categories' => ['Ostalo'],
        ]);

        $response->assertOk()->assertJson(['period' => '2026-07']);
    }

    public function test_chat_defaults_to_current_period_when_gemini_returns_an_invalid_one(): void
    {
        $this->travelTo('2026-07-15');
        $user = User::factory()->create();

        $this->fakeGemini([
            'action' => 'add_expense', 'name' => 'Kafa', 'amount' => 500,
            'currency' => 'RSD', 'freq' => 0, 'category' => 'Ostalo', 'period' => 'not-a-period',
        ]);

        $response = $this->actingAs($user)->postJson('/api/budget/chat', [
            'message' => 'potrošio sam 500 na kafu',
            'expense_categories' => ['Ostalo'],
            'savings_categories' => ['Ostalo'],
        ]);

        $response->assertOk()->assertJson(['period' => '2026-07']);
    }
}
