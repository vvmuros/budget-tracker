<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_user_can_delete_account_with_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->actingAs($user)->post('/account/delete', ['password' => 'correct-password']);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_password_user_cannot_delete_account_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->actingAs($user)->post('/account/delete', ['password' => 'wrong-password']);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_google_user_can_delete_account_by_typing_delete(): void
    {
        $user = User::factory()->create([
            'google_id' => '1234567890',
            'password' => Hash::make(\Illuminate\Support\Str::random(40)),
        ]);

        $response = $this->actingAs($user)->post('/account/delete', ['confirm_text' => 'DELETE']);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_google_user_cannot_delete_account_without_typing_delete_exactly(): void
    {
        $user = User::factory()->create([
            'google_id' => '1234567890',
            'password' => Hash::make(\Illuminate\Support\Str::random(40)),
        ]);

        $response = $this->actingAs($user)->post('/account/delete', ['confirm_text' => 'delete']);

        $response->assertSessionHasErrors('confirm_text');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
