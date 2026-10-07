<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\StripeWebhookTestHelper;
use Tests\TestCase;

#[Group('external-api')]
class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook_secret' => $this->webhookSecret,
        ]);
    }

    public function test_checkout_completed_with_valid_signature_grants_quota(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'quantity' => 3,
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_completed',
            'stripe_payment_intent_id' => null,
        ]);
        $payload = $this->checkoutCompletedPayload($payment, 'cs_test_completed', 'pi_test_completed');

        $response = $this->postStripeWebhook($payload);

        $response->assertOk();
        $response->assertJson(['received' => true]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
            'stripe_checkout_session_id' => 'cs_test_completed',
            'stripe_payment_intent_id' => 'pi_test_completed',
        ]);

        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertNotNull($payment->fresh()->quota_granted_at);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 3,
            'related_payment_id' => $payment->id,
            'note' => 'Stripe Checkout による追加面談購入',
        ]);
    }

    public function test_invalid_signature_is_rejected_and_does_not_grant_quota(): void
    {
        $payment = Payment::factory()->create([
            'quantity' => 2,
            'stripe_checkout_session_id' => 'cs_test_invalid_signature',
        ]);
        $payload = $this->checkoutCompletedPayload($payment, 'cs_test_invalid_signature', 'pi_test_invalid_signature');

        $response = $this
            ->call('POST', route('webhooks.stripe'), [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => StripeWebhookTestHelper::signature($payload, 'wrong-secret'),
            ], $payload);

        $response->assertBadRequest();
        $response->assertJson(['message' => 'Webhookの署名が不正です。']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Pending->value,
            'stripe_payment_intent_id' => null,
            'quota_granted_at' => null,
        ]);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $payment = Payment::factory()->create([
            'stripe_checkout_session_id' => 'cs_test_missing_signature',
        ]);
        $payload = $this->checkoutCompletedPayload($payment, 'cs_test_missing_signature', 'pi_test_missing_signature');

        $response = $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload);

        $response->assertBadRequest();
        $response->assertJson(['message' => 'Webhookの署名が不正です。']);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_duplicate_checkout_completed_delivery_is_idempotent(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'quantity' => 5,
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_duplicate',
            'stripe_payment_intent_id' => null,
        ]);
        $payload = $this->checkoutCompletedPayload($payment, 'cs_test_duplicate', 'pi_test_duplicate');

        $this->postStripeWebhook($payload)->assertOk();
        $this->postStripeWebhook($payload)->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
            'stripe_payment_intent_id' => 'pi_test_duplicate',
        ]);

        $this->assertNotNull($payment->fresh()->quota_granted_at);
        $this->assertDatabaseCount('meeting_quota_transactions', 1);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_checkout_expired_with_valid_signature_marks_pending_payment_failed(): void
    {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_expired',
        ]);
        $payload = StripeWebhookTestHelper::eventPayload(
            'evt_test_expired',
            'checkout.session.expired',
            [
                'id' => 'cs_test_expired',
                'object' => 'checkout.session',
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
            ],
        );

        $response = $this->postStripeWebhook($payload);

        $response->assertOk();
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Failed->value,
            'stripe_checkout_session_id' => 'cs_test_expired',
        ]);
        $this->assertNotNull($payment->fresh()->failed_at);
    }

    private function postStripeWebhook(string $payload): TestResponse
    {
        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => StripeWebhookTestHelper::signature($payload, $this->webhookSecret),
        ], $payload);
    }

    private function checkoutCompletedPayload(Payment $payment, string $sessionId, string $paymentIntentId): string
    {
        return StripeWebhookTestHelper::eventPayload(
            'evt_'.$sessionId,
            'checkout.session.completed',
            [
                'id' => $sessionId,
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'payment_intent' => $paymentIntentId,
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
            ],
        );
    }
}
