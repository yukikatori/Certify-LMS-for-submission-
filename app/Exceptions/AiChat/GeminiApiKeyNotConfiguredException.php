<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Gemini API キーが未設定の環境で AI 応答生成が試行された場合に throw する例外。
 *
 * 機能は有効だが外部 API 認証情報が未設定のため、AI 相談を一時利用不可として扱う。
 * 受講生向けには StoreMessageAction で案内文に変換し、詳細は error_detail に内部記録する。
 */
final class GeminiApiKeyNotConfiguredException extends ServiceUnavailableHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            retryAfter: null,
            message: 'Gemini API キーが未設定です。',
            previous: $previous,
        );
    }
}
