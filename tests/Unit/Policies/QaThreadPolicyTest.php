<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaThreadPolicy の判定を検証する Unit テスト。
 *
 * 質問掲示板投稿の認可ルール
 * - 受講生は公開済資格すべてのスレッドを閲覧・投稿できる
 * - コーチは担当資格のスレッドのみ閲覧・回答でき、担当外の資格は操作できない
 * - 公開停止中の資格のスレッドは受講生・コーチには見えない(管理者は閲覧できる)
 * - 受講中の受講生・コーチのみアクセスできる
 * - 管理者は専用画面から、公開停止中の資格を含む全資格のスレッドを横断的に閲覧できる
 */
class QaThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_returns_true_for_student_coach_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($coach));
        $this->assertTrue($policy->viewAny($student));
    }

    public function test_view_returns_expected_by_role_certification_status_and_assignment(): void
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

        $threadWithPublishedCertification = QaThread::factory()
            ->forStudent($student)
            ->forCertification($publishedCertification)
            ->create();

        $threadWithDraftCertification = QaThread::factory()
            ->forStudent($student)
            ->forCertification($draftCertification)
            ->create();

        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->view($admin, $threadWithPublishedCertification));
        $this->assertTrue($policy->view($assignedCoach, $threadWithPublishedCertification));
        $this->assertTrue($policy->view($student, $threadWithPublishedCertification));
        $this->assertFalse($policy->view($unassignedCoach, $threadWithPublishedCertification));

        $this->assertTrue($policy->view($admin, $threadWithDraftCertification));
        $this->assertFalse($policy->view($assignedCoach, $threadWithDraftCertification));
        $this->assertFalse($policy->view($student, $threadWithDraftCertification));
        $this->assertFalse($policy->view($unassignedCoach, $threadWithDraftCertification));
    }

    public function test_create_returns_true_only_for_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $policy = new QaThreadPolicy;

        $this->assertFalse($policy->create($admin));
        $this->assertFalse($policy->create($coach));
        $this->assertTrue($policy->create($student));
    }

    public function test_update_returns_true_only_for_author_on_published_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();

        $threadWithPublishedCertification = QaThread::factory()
            ->forStudent($student)
            ->forCertification($publishedCertification)
            ->create();

        $threadWithDraftCertification = QaThread::factory()
            ->forStudent($student)
            ->forCertification($draftCertification)
            ->create();

        $policy = new QaThreadPolicy;

        $this->assertFalse($policy->update($admin, $threadWithPublishedCertification));
        $this->assertFalse($policy->update($coach, $threadWithPublishedCertification));
        $this->assertTrue($policy->update($student, $threadWithPublishedCertification));
        $this->assertFalse($policy->update($otherStudent, $threadWithPublishedCertification));

        $this->assertFalse($policy->update($admin, $threadWithDraftCertification));
        $this->assertFalse($policy->update($coach, $threadWithDraftCertification));
        $this->assertFalse($policy->update($student, $threadWithDraftCertification));
        $this->assertFalse($policy->update($otherStudent, $threadWithDraftCertification));
    }

    public function test_delete_returns_true_for_author_or_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $certification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $draftThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($draftCertification)
            ->create();

        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->delete($admin, $thread));
        $this->assertFalse($policy->delete($coach, $thread));
        $this->assertTrue($policy->delete($student, $thread));
        $this->assertFalse($policy->delete($otherStudent, $thread));

        $this->assertFalse($policy->delete($student, $draftThread));
        $this->assertTrue($policy->delete($admin, $draftThread));
    }

    public function test_resolve_and_unresolve_return_true_only_for_author_on_published_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $certification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $draftThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($draftCertification)
            ->create();

        $policy = new QaThreadPolicy;

        $this->assertFalse($policy->resolve($admin, $thread));
        $this->assertFalse($policy->resolve($coach, $thread));
        $this->assertTrue($policy->resolve($student, $thread));
        $this->assertFalse($policy->resolve($otherStudent, $thread));

        $this->assertFalse($policy->unresolve($admin, $thread));
        $this->assertFalse($policy->unresolve($coach, $thread));
        $this->assertTrue($policy->unresolve($student, $thread));
        $this->assertFalse($policy->unresolve($otherStudent, $thread));

        $this->assertFalse($policy->resolve($student, $draftThread));
        $this->assertFalse($policy->unresolve($student, $draftThread));
    }
}
