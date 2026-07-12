<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    /**
     * @param  array<int, array{mime_type: string, data: string}>|null  $images  Inline images (base64 data, no data: prefix).
     */
    public function generate(string $prompt, ?array $responseSchema = null, ?array $images = null): string
    {
        $model = config('services.gemini.model');
        $key = config('services.gemini.key');

        $parts = [];
        foreach ($images ?? [] as $image) {
            $parts[] = ['inlineData' => ['mimeType' => $image['mime_type'], 'data' => $image['data']]];
        }
        $parts[] = ['text' => $prompt];

        $payload = [
            'contents' => [['parts' => $parts]],
        ];

        if ($responseSchema) {
            $payload['generationConfig'] = [
                'responseMimeType' => 'application/json',
                'responseSchema' => $responseSchema,
            ];
        }

        $response = Http::timeout(20)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->status());
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }
}
