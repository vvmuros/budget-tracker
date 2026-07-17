<?php

namespace Tests\Feature;

use App\Models\ExchangeRateSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    private function fakeNbsHtml(string $eur = '117,3751', string $usd = '102,6096'): void
    {
        Http::fake([
            'webappcenter.nbs.rs/*' => Http::response(<<<HTML
                <table class="table">
                <tbody>
                <tr><td>EUR</td><td>978</td><td>Evro</td><td>1</td><td>{$eur}</td></tr>
                <tr><td>USD</td><td>840</td><td>SAD</td><td>1</td><td>{$usd}</td></tr>
                </tbody>
                </table>
                HTML, 200),
        ]);
    }

    public function test_fetch_command_stores_todays_snapshot(): void
    {
        $this->fakeNbsHtml();

        $this->artisan('exchange-rate:fetch')->assertSuccessful();

        $this->assertDatabaseHas('exchange_rate_snapshots', [
            'date' => now()->toDateString(),
            'usd' => 102.6096,
            'eur' => 117.3751,
        ]);
    }

    public function test_fetch_command_is_idempotent_for_the_same_day(): void
    {
        $this->fakeNbsHtml();

        $this->artisan('exchange-rate:fetch')->assertSuccessful();
        $this->artisan('exchange-rate:fetch')->assertSuccessful();

        $this->assertSame(1, ExchangeRateSnapshot::count(), 'Running it twice the same day should update, not duplicate, the row');
    }

    public function test_latest_endpoint_returns_the_most_recent_snapshot(): void
    {
        $user = User::factory()->create();

        ExchangeRateSnapshot::create(['date' => '2026-07-10', 'usd' => 100, 'eur' => 110]);
        ExchangeRateSnapshot::create(['date' => '2026-07-17', 'usd' => 103, 'eur' => 117]);

        $response = $this->actingAs($user)->getJson('/api/exchange-rate/latest');

        $response->assertOk()->assertJson(['date' => '2026-07-17', 'usd' => 103, 'eur' => 117]);
    }

    public function test_history_endpoint_returns_daily_points_for_7d_range(): void
    {
        $this->travelTo('2026-07-17');
        $user = User::factory()->create();

        ExchangeRateSnapshot::create(['date' => '2026-07-15', 'usd' => 102, 'eur' => 117]);
        ExchangeRateSnapshot::create(['date' => '2026-07-16', 'usd' => 102.5, 'eur' => 117.2]);
        ExchangeRateSnapshot::create(['date' => '2026-06-01', 'usd' => 99, 'eur' => 110]);

        $response = $this->actingAs($user)->getJson('/api/exchange-rate/history?range=7d');

        $response->assertOk();
        $points = $response->json('points');
        $this->assertCount(2, $points, 'Only points within the last 7 days should be returned');
    }
}
