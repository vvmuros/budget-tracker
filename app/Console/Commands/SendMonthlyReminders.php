<?php

namespace App\Console\Commands;

use App\Http\Controllers\PushController;
use Illuminate\Console\Command;

class SendMonthlyReminders extends Command
{
    protected $signature = 'reminders:send-monthly';

    protected $description = "Notify subscribed users about last month's leftover to save";

    public function handle(PushController $push): int
    {
        $result = $push->sendMonthlyRemindersToAll();

        $this->info("Period {$result['period']}: notified {$result['notified']} user(s).");

        return self::SUCCESS;
    }
}
