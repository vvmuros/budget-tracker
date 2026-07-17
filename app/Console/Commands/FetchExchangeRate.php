<?php

namespace App\Console\Commands;

use App\Models\ExchangeRateSnapshot;
use App\Services\NbsRateService;
use Illuminate\Console\Command;

class FetchExchangeRate extends Command
{
    protected $signature = 'exchange-rate:fetch';

    protected $description = "Record today's official NBS middle exchange rate";

    public function handle(NbsRateService $nbs): int
    {
        $rates = $nbs->fetchCurrentMiddleRate();

        if (! $rates) {
            $this->error('Could not read the NBS rate page.');

            return self::FAILURE;
        }

        ExchangeRateSnapshot::updateOrCreate(
            ['date' => now()->toDateString()],
            ['usd' => $rates['usd'], 'eur' => $rates['eur']]
        );

        $this->info("Saved rates for ".now()->toDateString().": USD={$rates['usd']} EUR={$rates['eur']}");

        return self::SUCCESS;
    }
}
