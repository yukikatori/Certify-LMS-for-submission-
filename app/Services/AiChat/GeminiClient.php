<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Exceptions\AiChat\GeminiApiKeyNotConfiguredException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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
        $retryAttempts = max(0, (int) config('ai-chat.gemini.retry_attempts', 1));

        $startedAt = microtime(true);

        $response = $this->postWithRetry(
            "{$baseUrl}/models/{$model}:generateContent",
            $payload,
            $apiKey,
            $timeout,
            $retryAttempts,
        );

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

    private function postWithRetry(
        string $url,
        array $payload,
        string $apiKey,
        int $timeout,
        int $retryAttempts,
    ): Response {
        $attempt = 0;
        $maxAttempts = $retryAttempts + 1;
        $lastConnectionException = null;

        do {
            $attempt++;

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $payload);

                if (! $this->shouldRetryResponse($response) || $attempt >= $maxAttempts) {
                    return $response;
                }
            } catch (ConnectionException $e) {
                $lastConnectionException = $e;

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
            }
        } while ($attempt < $maxAttempts);

        throw $lastConnectionException ?? new RuntimeException('Gemini request retry failed.');
    }

    private function shouldRetryResponse(Response $response): bool
    {
        return in_array($response->status(), [429, 500, 502, 503, 504], true);
    }
}
