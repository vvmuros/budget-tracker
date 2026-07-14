<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribing_stores_a_push_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example-endpoint',
            'keys' => ['p256dh' => 'fake-p256dh', 'auth' => 'fake-auth'],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example-endpoint',
        ]);
    }

    public function test_unsubscribing_removes_the_subscription(): void
    {
        $user = User::factory()->create();

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example-endpoint',
            'p256dh' => 'fake-p256dh',
            'auth' => 'fake-auth',
        ]);

        $this->actingAs($user)->postJson('/api/push/unsubscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example-endpoint',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $user->id]);
    }

    public function test_monthly_reminder_cron_rejects_wrong_or_missing_token(): void
    {
        config(['services.cron.secret' => 'correct-secret']);

        $this->postJson('/cron/monthly-reminder')->assertForbidden();
        $this->postJson('/cron/monthly-reminder?token=wrong')->assertForbidden();
    }

    public function test_monthly_reminder_cron_succeeds_with_correct_token(): void
    {
        config(['services.cron.secret' => 'correct-secret']);

        // No users have push subscriptions, so this never attempts an actual
        // network send — it just confirms the endpoint accepts the token.
        $this->postJson('/cron/monthly-reminder?token=correct-secret')
            ->assertOk()
            ->assertJson(['notified' => 0]);
    }

    public function test_send_test_reports_nothing_sent_without_a_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/push/test')
            ->assertOk()
            ->assertJson(['sent' => false]);
    }
}
