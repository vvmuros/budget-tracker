<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\BudgetCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushController extends Controller
{
    public function __construct(private BudgetCalculator $calc)
    {
    }

    public function publicKey()
    {
        return response()->json(['key' => config('services.vapid.public_key')]);
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint']],
            ['p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth']]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        $request->user()->pushSubscriptions()->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Lets the logged-in user fire a reminder at themselves right now, so the
     * monthly flow can be tested without waiting for the 1st of the month.
     */
    public function sendTest(Request $request)
    {
        $lang = $request->cookie('lang', 'sr');
        $sent = $this->sendReminderToUser($request->user(), now()->subMonthNoOverflow()->format('Y-m'), $lang, true);

        return response()->json(['sent' => $sent]);
    }

    /**
     * Called by the app:send-monthly-reminders scheduled command (see
     * app/Console/Kernel.php) on the 1st of the month — sends every
     * subscribed user a reminder about last month's leftover, if there was any.
     */
    public function sendMonthlyRemindersToAll(): array
    {
        $period = now()->subMonthNoOverflow()->format('Y-m');
        $sentCount = 0;

        User::whereHas('pushSubscriptions')->get()->each(function (User $user) use ($period, &$sentCount) {
            if ($this->sendReminderToUser($user, $period, 'sr', false)) {
                $sentCount++;
            }
        });

        return ['period' => $period, 'notified' => $sentCount];
    }

    private function sendReminderToUser(User $user, string $period, string $lang, bool $force): bool
    {
        $subscriptions = $user->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            return false;
        }

        $net = $this->netForPeriod($user, $period);

        if (! $force && ($net === null || $net <= 0)) {
            return false;
        }

        $amount = $net !== null ? number_format((int) round($net), 0, ',', '.') : '0';
        $monthName = $this->monthLabel($period, $lang);

        if ($lang === 'en') {
            $body = $net !== null && $net > 0
                ? "You have +{$amount} RSD left over from {$monthName}. Open Bilanso to log it as savings."
                : "Don't forget to check {$monthName} in Bilanso.";
        } else {
            $body = $net !== null && $net > 0
                ? "Ostalo ti je +{$amount} RSD iz {$monthName}. Otvori Bilanso da to upišeš u štednju."
                : "Ne zaboravi da proveriš {$monthName} u Bilanso-u.";
        }

        $this->pushToSubscriptions($subscriptions, 'Bilanso', $body);

        return true;
    }

    private function netForPeriod(User $user, string $period): ?float
    {
        $income = $this->calc->resolveEffectiveValue($user, 'income-items', $period);
        $expense = $this->calc->resolveEffectiveValue($user, 'expense-items', $period);
        $rates = $this->calc->resolveEffectiveValue($user, 'expense-rates', $period);

        if (! $income && ! $expense) {
            return null;
        }

        return $this->calc->calculateNet(collect([
            'income-items' => $income['value'] ?? '[]',
            'expense-items' => $expense['value'] ?? '[]',
            'expense-rates' => $rates['value'] ?? '{}',
        ]), $period);
    }

    private function monthLabel(string $period, string $lang): string
    {
        $namesSr = ['januar', 'februar', 'mart', 'april', 'maj', 'jun', 'jul', 'avgust', 'septembar', 'oktobar', 'novembar', 'decembar'];
        $namesEn = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        [$year, $month] = array_map('intval', explode('-', $period));
        $name = ($lang === 'en' ? $namesEn : $namesSr)[$month - 1];

        return $lang === 'en' ? "{$name} {$year}" : "{$name}a {$year}.";
    }

    private function pushToSubscriptions(Collection $subscriptions, string $title, string $body): void
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.vapid.subject'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => '/']);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                    'contentEncoding' => 'aes128gcm',
                ]),
                $payload
            );
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }
}
