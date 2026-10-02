<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaReplyPolicy の判定を検証する Unit テスト。
 *
 * 質問掲示板回答の認可ルール
 * - 受講生は公開済資格すべてのスレッドを閲覧・投稿できる
 * - コーチは担当資格のスレッドのみ閲覧・回答でき、担当外の資格は操作できない
 * - 公開停止中の資格のスレッドは受講生・コーチには見えない(管理者は閲覧できる)
 * - 受講中の受講生・コーチのみアクセスできる
 * - 管理者は専用画面から、公開停止中の資格を含む全資格のスレッドを横断的に閲覧できる
 */
class QaReplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_returns_expected_by_role_certification_status_and_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $assignedCoach = User::factory()->coach()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $publishedCertification->id,
            'user_id' => $assignedCoach->id,
        ]);

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $draftCertification->id,
            'user_id' => $assignedCoach->id,
        ]);

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($publishedCertification)
            ->create();

        $draftThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($draftCertification)
            ->create();

        $policy = new QaReplyPolicy;

        $this->assertFalse($policy->create($admin, $thread));
        $this->assertTrue($policy->create($assignedCoach, $thread));
        $this->assertTrue($policy->create($student, $thread));
        $this->assertFalse($policy->create($unassignedCoach, $thread));

        $this->assertFalse($policy->create($admin, $draftThread));
        $this->assertFalse($policy->create($assignedCoach, $draftThread));
        $this->assertFalse($policy->create($student, $draftThread));
        $this->assertFalse($policy->create($unassignedCoach, $draftThread));
    }

    public function test_update_returns_expected_for_reply_author_role_and_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $assignedCoach = User::factory()->coach()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $publishedCertification->id,
            'user_id' => $assignedCoach->id,
        ]);

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $draftCertification->id,
            'user_id' => $assignedCoach->id,
        ]);

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($publishedCertification)
            ->create();

        $draftThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($draftCertification)
            ->create();

        $replyByStudent = QaReply::factory()
            ->forThread($thread)
            ->forStudent($student)
            ->create();

        $replyByCoach = QaReply::factory()
            ->forThread($thread)
            ->forCoach($assignedCoach)
            ->create();

        $draftReplyByStudent = QaReply::factory()
            ->forThread($draftThread)
            ->forStudent($student)
            ->create();

        $draftReplyByCoach = QaReply::factory()
            ->forThread($draftThread)
            ->forCoach($assignedCoach)
            ->create();

        $policy = new QaReplyPolicy;

        $this->assertFalse($policy->update($admin, $replyByStudent));
        $this->assertFalse($policy->update($assignedCoach, $replyByStudent));
        $this->assertTrue($policy->update($student, $replyByStudent));
        $this->assertFalse($policy->update($unassignedCoach, $replyByStudent));

        $this->assertFalse($policy->update($admin, $replyByCoach));
        $this->assertTrue($policy->update($assignedCoach, $replyByCoach));
        $this->assertFalse($policy->update($student, $replyByCoach));
        $this->assertFalse($policy->update($unassignedCoach, $replyByCoach));

        $this->assertFalse($policy->update($admin, $draftReplyByStudent));
        $this->assertFalse($policy->update($assignedCoach, $draftReplyByStudent));
        $this->assertFalse($policy->update($student, $draftReplyByStudent));
        $this->assertFalse($policy->update($unassignedCoach, $draftReplyByStudent));

        $this->assertFalse($policy->update($admin, $draftReplyByCoach));
        $this->assertFalse($policy->update($assignedCoach, $draftReplyByCoach));
        $this->assertFalse($policy->update($student, $draftReplyByCoach));
        $this->assertFalse($policy->update($unassignedCoach, $draftReplyByCoach));
    }

    public function test_delete_returns_true_for_reply_author_or_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $assignedCoach = User::factory()->coach()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $publishedCertification->id,
            'user_id' => $assignedCoach->id,
        ]);

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $draftCertification->id,
            'user_id' => $assignedCoach->id,
        ]);

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($publishedCertification)
            ->create();

        $draftThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($draftCertification)
            ->create();

        $replyByStudent = QaReply::factory()
            ->forThread($thread)
            ->forStudent($student)
            ->create();

        $replyByCoach = QaReply::factory()
            ->forThread($thread)
            ->forCoach($assignedCoach)
            ->create();

        $draftReplyByStudent = QaReply::factory()
            ->forThread($draftThread)
            ->forStudent($student)
            ->create();

        $draftReplyByCoach = QaReply::factory()
            ->forThread($draftThread)
            ->forCoach($assignedCoach)
            ->create();

        $policy = new QaReplyPolicy;

        $this->assertTrue($policy->delete($admin, $replyByStudent));
        $this->assertFalse($policy->delete($assignedCoach, $replyByStudent));
        $this->assertTrue($policy->delete($student, $replyByStudent));
        $this->assertFalse($policy->delete($otherStudent, $replyByStudent));
        $this->assertFalse($policy->delete($unassignedCoach, $replyByStudent));

        $this->assertTrue($policy->delete($admin, $replyByCoach));
        $this->assertTrue($policy->delete($assignedCoach, $replyByCoach));
        $this->assertFalse($policy->delete($student, $replyByCoach));
        $this->assertFalse($policy->delete($otherStudent, $replyByCoach));
        $this->assertFalse($policy->delete($unassignedCoach, $replyByCoach));

        $this->assertTrue($policy->delete($admin, $draftReplyByStudent));
        $this->assertFalse($policy->delete($student, $draftReplyByStudent));

        $this->assertTrue($policy->delete($admin, $draftReplyByCoach));
        $this->assertFalse($policy->delete($assignedCoach, $draftReplyByCoach));
    }
}
