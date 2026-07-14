<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EnsureGeminiQuota
{
    public function handle(Request $request, Closure $next)
    {
        $limit = (int) config('services.gemini.daily_limit_per_user', 20);

        if ($limit <= 0) {
            return $next($request);
        }

        $key = 'gemini-quota:'.$request->user()->id.':'.now()->format('Y-m-d');
        $count = (int) Cache::get($key, 0);

        if ($count >= $limit) {
            $lang = $request->cookie('lang', 'sr');
            $message = $lang === 'en'
                ? "You've used all {$limit} free AI requests for today. It resets at midnight — or upgrade for unlimited access."
                : "Iskoristio si svih {$limit} besplatnih AI upita za danas. Reset je u ponoć — ili nadogradi nalog za neograničen pristup.";

            return response()->json(['error' => $message, 'quotaExceeded' => true], 429);
        }

        Cache::put($key, $count + 1, now()->endOfDay());

        return $next($request);
    }
}
