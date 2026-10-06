<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\MeetingQuota\HandleCheckoutCompletedAction;
use App\UseCases\MeetingQuota\HandleCheckoutFailedAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe から送られる追加面談購入イベントの公開受信口。
 * 署名検証を実施し、決済結果の通知に応じActionにて完了した購入の分だけ残面談回数を加算
 */
class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        HandleCheckoutCompletedAction $handleCheckoutCompleted,
        HandleCheckoutFailedAction $handleCheckoutFailed,
    ): JsonResponse {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Stripe Webhook secret が未設定です。'], 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['message' => 'Webhookの署名が不正です。'], 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $handleCheckoutCompleted($event),
            'checkout.session.expired' => $handleCheckoutFailed($event),
            default => null,
        };

        return response()->json(['received' => true]);
    }
}
