<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Stripe\Event;

/**
 * Stripe Checkout 期限切れイベントを処理し、未完了の購入だけ失敗扱いにする。
 */
final class HandleCheckoutFailedAction
{
    public function __invoke(Event $event): void
    {
        $session = $event->data->object;
        $paymentId = $session->metadata->payment_id ?? null;

        if (! is_string($paymentId) || $paymentId === '') {
            return;
        }

        DB::transaction(function () use ($session, $paymentId): void {
            $payment = Payment::query()
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return;
            }

            if ($payment->status === PaymentStatus::Succeeded || $payment->quota_granted_at !== null) {
                return;
            }

            if (
                $payment->stripe_checkout_session_id !== null
                && $payment->stripe_checkout_session_id !== $session->id
            ) {
                return;
            }

            $payment->update([
                'status' => PaymentStatus::Failed,
                'stripe_checkout_session_id' => $session->id,
                'failed_at' => now(),
            ]);
        });
    }
}
