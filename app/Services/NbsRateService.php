<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;

/**
 * Reads today's official middle exchange rate straight off NBS's public
 * (no-auth, no-API-key) rate page — there's no public JSON/XML API without
 * enrolling as a registered business in their web-services system, so this
 * is a plain HTML scrape of a page meant for public viewing.
 */
class NbsRateService
{
    private const URL = 'https://webappcenter.nbs.rs/ExchangeRateWebApp/ExchangeRate/CurrentMiddleRate';

    /**
     * @return array{usd: float, eur: float}|null
     */
    public function fetchCurrentMiddleRate(): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])->timeout(15)->get(self::URL);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->body());
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table[contains(@class, "table")]//tbody//tr');

        $rates = [];
        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);
            if ($cells->length < 5) {
                continue;
            }

            $code = trim($cells->item(0)->textContent);
            $unit = (float) str_replace(',', '.', trim($cells->item(3)->textContent));
            $rate = (float) str_replace(',', '.', trim($cells->item(4)->textContent));

            if ($unit <= 0) {
                continue;
            }

            if ($code === 'EUR') {
                $rates['eur'] = round($rate / $unit, 4);
            } elseif ($code === 'USD') {
                $rates['usd'] = round($rate / $unit, 4);
            }
        }

        return isset($rates['usd'], $rates['eur']) ? $rates : null;
    }
}
