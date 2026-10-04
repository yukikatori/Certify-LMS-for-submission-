<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Announcement;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AnnouncementPolicy の ability × Role マトリクス検証。
 * お知らせ配信は管理者専用機能のため、viewAny / view / create の全 ability で admin のみ true、
 * coach / student は false を返すことを検証する。
 */
class AnnouncementPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者お知らせ ability × Role のマトリクス検証。
     * 配信履歴の閲覧と新規配信は admin のみ許可し、coach / student には一切許可しない。
     */
    #[DataProvider('adminOnlyAbilityMatrix')]
    public function test_admin_only_abilities_match_role_expectation(
        string $actingRole,
        string $policyMethod,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $admin = User::factory()->admin()->create();
        $announcement = $this->createAnnouncement($admin);
        $policy = new AnnouncementPolicy;

        // Act
        $result = $policyMethod === 'create' || $policyMethod === 'viewAny'
            ? $policy->{$policyMethod}($actor)
            : $policy->{$policyMethod}($actor, $announcement);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} が {$policyMethod} で ".($expected ? 'true' : 'false').' を返すべきだが反対の結果が返った',
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function adminOnlyAbilityMatrix(): array
    {
        $abilities = ['viewAny', 'view', 'create'];
        $roles = [
            'admin' => true,
            'coach' => false,
            'student' => false,
        ];

        $cases = [];
        foreach ($roles as $role => $expected) {
            foreach ($abilities as $ability) {
                $caseKey = $expected
                    ? "{$role} は {$ability} を実行できる"
                    : "{$role} は {$ability} を実行できない";
                $cases[$caseKey] = [$role, $ability, $expected];
            }
        }

        return $cases;
    }

    private function createAnnouncement(User $admin): Announcement
    {
        $announcement = new Announcement;
        $announcement->forceFill([
            'title' => 'お知らせ',
            'body' => '本文です。',
            'target_type' => 'all',
            'target_certification_id' => null,
            'target_user_id' => null,
            'dispatched_count' => 0,
            'dispatched_at' => now(),
            'created_by' => $admin->id,
        ]);
        $announcement->save();

        return $announcement;
    }
}
