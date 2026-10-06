<?php

declare(strict_types=1);

namespace App\Services\AiChat;

/**
 * Gemini API のレスポンス配列を、アプリ内で扱いやすい値オブジェクトに変換するサービス。
 */
final readonly class GeminiResponse
{
    public function __construct(
        public string $text,
        public string $model,
        public int $latencyMs,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public ?int $totalTokens,
    ) {}

    public static function fromArray(array $json, string $model, int $latencyMs): self
    {
        $text = data_get($json, 'candidates.0.content.parts.0.text', '');
        $usage = $json['usageMetadata'] ?? [];

        return new self(
            text: (string) $text,
            model: (string) ($json['modelVersion'] ?? $model),
            latencyMs: $latencyMs,
            promptTokens: $usage['promptTokenCount'] ?? null,
            completionTokens: $usage['candidatesTokenCount'] ?? null,
            totalTokens: $usage['totalTokenCount'] ?? null,
        );
    }
}
