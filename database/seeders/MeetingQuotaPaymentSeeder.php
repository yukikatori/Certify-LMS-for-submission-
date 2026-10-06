<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingPackStatus;
use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 開発用の追加面談購入データ。
 *
 * succeeded のみ MeetingQuotaTransaction(purchased) を作成し、pending / failed は残数に反映しない。
 */
class MeetingQuotaPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $packs = MeetingPack::query()
            ->where('status', MeetingPackStatus::Published->value)
            ->ordered()
            ->get();

        if ($packs->isEmpty()) {
            $this->command?->warn('MeetingQuotaPaymentSeeder: 公開中の MeetingPack が存在しません。');

            return;
        }

        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        $noQuotaStudent = User::query()->where('email', 'student-noquota@certify-lms.test')->first();
        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', [
                'student@certify-lms.test',
                'student-noquota@certify-lms.test',
            ])
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        if ($fixedStudent !== null) {
            $this->createPayment($fixedStudent, $packs[0], PaymentStatus::Succeeded, now()->subDays(9));
            $this->createPayment($fixedStudent, $packs[1] ?? $packs[0], PaymentStatus::Pending, now()->subDays(2));
            $this->createPayment($fixedStudent, $packs[2] ?? $packs[0], PaymentStatus::Failed, now()->subDay());
        }

        if ($noQuotaStudent !== null) {
            $this->createPayment($noQuotaStudent, $packs[0], PaymentStatus::Pending, now()->subHours(10));
            $this->createPayment($noQuotaStudent, $packs[1] ?? $packs[0], PaymentStatus::Failed, now()->subHours(4));
        }

        $this->seedDemoStudents($demoStudents, $packs);
    }

    /**
     * @param Collection<int, User> $students
     * @param Collection<int, MeetingPack> $packs
     */
    private function seedDemoStudents(Collection $students, Collection $packs): void
    {
        foreach ($students->values() as $index => $student) {
            $status = match ($index) {
                0 => PaymentStatus::Succeeded,
                1 => PaymentStatus::Pending,
                default => PaymentStatus::Failed,
            };

            $this->createPayment($student, $packs[$index] ?? $packs[0], $status, now()->subDays(6 - $index));
        }
    }

    private function createPayment(User $student, MeetingPack $pack, PaymentStatus $status, \DateTimeInterface $occurredAt): Payment
    {
        $suffix = strtolower((string) str($student->id.'-'.$pack->id.'-'.$status->value)->replace(['-', '_'], ''));
        $paidAt = $status === PaymentStatus::Succeeded ? $occurredAt : null;
        $failedAt = $status === PaymentStatus::Failed ? $occurredAt : null;

        $payment = Payment::query()->forceCreate([
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'amount' => $pack->price,
            'currency' => 'JPY',
            'quantity' => $pack->meeting_count,
            'status' => $status,
            'stripe_checkout_session_id' => 'cs_test_seed_'.$suffix,
            'stripe_payment_intent_id' => $status === PaymentStatus::Succeeded ? 'pi_seed_'.$suffix : null,
            'paid_at' => $paidAt,
            'failed_at' => $failedAt,
            'quota_granted_at' => $paidAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);

        if ($status === PaymentStatus::Succeeded) {
            MeetingQuotaTransaction::query()->forceCreate([
                'user_id' => $student->id,
                'type' => MeetingQuotaTransactionType::Purchased->value,
                'amount' => $pack->meeting_count,
                'related_payment_id' => $payment->id,
                'note' => 'Seeder: Stripe Checkout 完了デモ',
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }

        return $payment;
    }
}
