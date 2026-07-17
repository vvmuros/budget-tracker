<?php

namespace Tests\Feature;

use App\Models\BudgetData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_pdf_report_downloads_successfully(): void
    {
        $this->travelTo('2026-07-15');

        $user = User::factory()->create();

        BudgetData::create(['user_id' => $user->id, 'key' => 'income-items', 'period' => '2026-07', 'value' => json_encode([
            ['id' => 'i1', 'name' => 'Plata', 'amount' => 130000, 'currency' => 'RSD', 'freq' => 1, 'active' => true],
        ])]);
        BudgetData::create(['user_id' => $user->id, 'key' => 'expense-items', 'period' => '2026-07', 'value' => json_encode([
            ['id' => 'e1', 'name' => 'Kirija', 'amount' => 20000, 'currency' => 'RSD', 'freq' => 1, 'active' => true, 'category' => 'Stanovanje'],
        ])]);
        BudgetData::create(['user_id' => $user->id, 'key' => 'savings-items', 'period' => 'global', 'value' => json_encode([
            ['id' => 's1', 'name' => 'Ušteđevina', 'amount' => 200000, 'currency' => 'RSD', 'category' => 'Štednja'],
        ])]);

        $response = $this->actingAs($user)->get('/api/budget/report/pdf?period=2026-07');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_monthly_pdf_report_rejects_invalid_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/api/budget/report/pdf?period=not-a-period')
            ->assertStatus(422);
    }
}
