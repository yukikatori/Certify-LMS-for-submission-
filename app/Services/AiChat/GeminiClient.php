<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Exceptions\AiChat\GeminiApiKeyNotConfiguredException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gemini APIを呼び出して、アプリで扱いやすい GeminiResponse に変換する外部APIアダプタ
 */
final class GeminiClient
{
    public function generate(array $payload): GeminiResponse
    {
        $apiKey = (string) config('ai-chat.gemini.api_key');

        if ($apiKey === '') {
            throw new GeminiApiKeyNotConfiguredException;
        }

        $model = (string) config('ai-chat.gemini.model', 'gemini-2.5-flash');
        $baseUrl = (string) config('ai-chat.gemini.base_url');
        $timeout = (int) config('ai-chat.gemini.timeout', 30);

        $startedAt = microtime(true);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post("{$baseUrl}/models/{$model}:generateContent", $payload);

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Gemini request failed: HTTP %d %s',
                $response->status(),
                substr($response->body(), 0, 1000),
            ));
        }

        $json = $response->json();

        return GeminiResponse::fromArray($json, $model, $latencyMs);
    }
}
