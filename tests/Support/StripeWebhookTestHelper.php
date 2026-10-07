<?php

declare(strict_types=1);

namespace Tests\Support;

final class StripeWebhookTestHelper
{
    public static function signature(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * @param array<string, mixed> $object
     */
    public static function eventPayload(string $eventId, string $type, array $object): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2026-09-30.clover',
            'created' => 1_779_238_400,
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => [
                'id' => null,
                'idempotency_key' => null,
            ],
            'type' => $type,
            'data' => [
                'object' => $object,
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
