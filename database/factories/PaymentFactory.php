<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $quantity = fake()->randomElement([1, 3, 5]);

        return [
            'user_id' => User::factory()->student(),
            'meeting_pack_id' => MeetingPack::factory()->published(),
            'amount' => $quantity * fake()->numberBetween(2500, 3500),
            'currency' => 'JPY',
            'quantity' => $quantity,
            'status' => PaymentStatus::Pending->value,
            'stripe_checkout_session_id' => 'cs_test_'.fake()->unique()->bothify('????????????'),
            'stripe_payment_intent_id' => null,
            'paid_at' => null,
            'failed_at' => null,
            'quota_granted_at' => null,
        ];
    }

    public function succeeded(?string $paymentIntentId = null): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Succeeded->value,
            'stripe_payment_intent_id' => $paymentIntentId ?? 'pi_test_'.fake()->unique()->bothify('????????????'),
            'paid_at' => now(),
            'quota_granted_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed->value,
            'failed_at' => now(),
        ]);
    }
}
