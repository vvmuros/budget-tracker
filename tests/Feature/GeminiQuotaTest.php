<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GeminiQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function registerTestRoute(): void
    {
        Route::middleware(['web', 'auth', 'gemini.quota'])
            ->post('/__test/gemini-quota', fn () => response()->json(['ok' => true]));
    }

    public function test_requests_within_the_daily_limit_pass_through(): void
    {
        config(['services.gemini.daily_limit_per_user' => 2]);
        $this->registerTestRoute();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/__test/gemini-quota')->assertOk();
        $this->actingAs($user)->postJson('/__test/gemini-quota')->assertOk();
    }

    public function test_requests_beyond_the_daily_limit_are_rejected(): void
    {
        config(['services.gemini.daily_limit_per_user' => 2]);
        $this->registerTestRoute();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/__test/gemini-quota')->assertOk();
        $this->actingAs($user)->postJson('/__test/gemini-quota')->assertOk();
        $this->actingAs($user)->postJson('/__test/gemini-quota')
            ->assertStatus(429)
            ->assertJson(['quotaExceeded' => true]);
    }

    public function test_the_limit_is_tracked_per_user(): void
    {
        config(['services.gemini.daily_limit_per_user' => 1]);
        $this->registerTestRoute();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->postJson('/__test/gemini-quota')->assertOk();
        $this->actingAs($userA)->postJson('/__test/gemini-quota')->assertStatus(429);
        $this->actingAs($userB)->postJson('/__test/gemini-quota')->assertOk();
    }

    public function test_a_zero_limit_disables_the_cap(): void
    {
        config(['services.gemini.daily_limit_per_user' => 0]);
        $this->registerTestRoute();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->postJson('/__test/gemini-quota')->assertOk();
        }
    }
}
