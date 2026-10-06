<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 受講生向け追加面談購入 Controller の HTTP 統合テスト。
 */
class MeetingQuotaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        $plan = Plan::factory()->published()->create();

        return User::factory()->student()->inProgress()->withPlan($plan)->create();
    }

    public function test_student_can_view_checkout_page_with_published_packs(): void
    {
        $student = $this->student();
        $published = MeetingPack::factory()->published()->create(['sort_order' => 10]);
        $draft = MeetingPack::factory()->draft()->create(['sort_order' => 20]);
        $archived = MeetingPack::factory()->archived()->create(['sort_order' => 30]);

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.select'));

        $response->assertOk();
        $response->assertViewIs('meeting-quota.checkout-select');
        $response->assertViewHas('plans', fn ($plans) => $plans->contains('id', $published->id)
            && ! $plans->contains('id', $draft->id)
            && ! $plans->contains('id', $archived->id));
    }

    public function test_admin_cannot_view_checkout_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('meeting-quota.checkout.select'))
            ->assertForbidden();
    }

    public function test_coach_cannot_view_checkout_page(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('meeting-quota.checkout.select'))
            ->assertForbidden();
    }

    public function test_guest_cannot_view_checkout_page(): void
    {
        $this->get(route('meeting-quota.checkout.select'))
            ->assertRedirect(route('login'));
    }

    public function test_student_cannot_start_checkout_with_invalid_payload(): void
    {
        $student = $this->student();

        $response = $this->actingAs($student)->postJson(
            route('meeting-quota.checkout.create'),
            ['meeting_pack_id' => 'not-a-ulid'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('meeting_pack_id');
    }

    public function test_student_can_view_success_page_with_own_payment(): void
    {
        $student = $this->student();
        $pack = MeetingPack::factory()->published()->create();
        $payment = $this->payment($student, $pack);

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.success', [
            'payment' => $payment->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('meeting-quota.success');
        $response->assertViewHas('payment', fn (?Payment $viewPayment) => $viewPayment?->is($payment) ?? false);
    }

    public function test_success_page_does_not_expose_other_users_payment(): void
    {
        $student = $this->student();
        $other = $this->student();
        $pack = MeetingPack::factory()->published()->create();
        $payment = $this->payment($other, $pack);

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.success', [
            'payment' => $payment->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('meeting-quota.success');
        $response->assertViewHas('payment', null);
    }

    public function test_success_page_can_be_viewed_without_payment_query(): void
    {
        $student = $this->student();

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.success'));

        $response->assertOk();
        $response->assertViewIs('meeting-quota.success');
        $response->assertViewHas('payment', null);
    }

    private function payment(User $student, MeetingPack $pack): Payment
    {
        return Payment::query()->forceCreate([
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'amount' => $pack->price,
            'currency' => 'JPY',
            'quantity' => $pack->meeting_count,
            'status' => PaymentStatus::Succeeded->value,
            'stripe_checkout_session_id' => 'cs_test_'.$student->id,
            'stripe_payment_intent_id' => 'pi_test_'.$student->id,
            'paid_at' => now(),
            'quota_granted_at' => now(),
        ]);
    }
}
