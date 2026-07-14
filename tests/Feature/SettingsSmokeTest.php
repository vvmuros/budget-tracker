<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $response->assertSee('Podešavanja');
    }

    public function test_book_page_renders_with_the_account_dropdown_instead_of_the_old_delete_box(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('user-menu-trigger', false);
        $response->assertDontSee('delete-account-box', false);
    }
}
