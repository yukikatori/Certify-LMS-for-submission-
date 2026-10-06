<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * 追加面談購入の Stripe Checkout Session を作成する。
 */
final class StoreAction
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, string $meetingPackId): string
    {
        $plan = MeetingPack::query()->findOrFail($meetingPackId);

        if (! $user->can('purchase', $plan)) {
            throw new AuthorizationException;
        }

        $payment = DB::transaction(fn () => Payment::create([
            'user_id' => $user->id,
            'meeting_pack_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => 'JPY',
            'quantity' => $plan->meeting_count,
            'status' => PaymentStatus::Pending,
        ]));

        $stripe = new StripeClient((string) config('services.stripe.secret'));

        /** @var Session $session */
        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => route('meeting-quota.checkout.success', [
                'payment' => $payment->id,
            ]).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('meeting-quota.checkout.select'),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => $payment->amount,
                    'product_data' => [
                        'name' => $plan->name,
                        'description' => $plan->description,
                    ],
                ],
            ]],
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'meeting_pack_id' => $plan->id,
            ],
        ]);

        $payment->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return (string) $session->url;
    }
}
