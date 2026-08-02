<?php

namespace App\Console\Commands;

use App\Http\Controllers\PushController;
use Illuminate\Console\Command;

class SendDailySpendingReminders extends Command
{
    protected $signature = 'reminders:send-daily';

    protected $description = 'Nudge subscribed users every evening to log today\'s spending';

    public function handle(PushController $push): int
    {
        $result = $push->sendDailyRemindersToAll();

        $this->info("Notified {$result['notified']} user(s).");

        return self::SUCCESS;
    }
}
