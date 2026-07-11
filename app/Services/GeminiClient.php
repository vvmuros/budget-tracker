<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    public function generate(string $prompt, ?array $responseSchema = null): string
    {
        $model = config('services.gemini.model');
        $key = config('services.gemini.key');

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
        ];

        if ($responseSchema) {
            $payload['generationConfig'] = [
                'responseMimeType' => 'application/json',
                'responseSchema' => $responseSchema,
            ];
        }

        $response = Http::timeout(15)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->status());
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }
}
