<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * EnrollmentGoal に対する認可ポリシー。
 * - create / update / delete / markAchieved / unmarkAchieved : 受講生本人のみ
 *
 * 受講中資格に紐づく個人目標はEnrollmentPolicyのviewにて管理し、
 * コーチは担当資格に登録した受講生の目標一覧を閲覧でき、管理者は任意受講生の目標一覧を閲覧できる。
 */
class EnrollmentGoalPolicy
{
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id
            && $enrollment->status === EnrollmentStatus::Learning;
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    private function canManage(User $user, EnrollmentGoal $goal): bool
    {
        $goal->loadMissing('enrollment');

        return $user->role === UserRole::Student
            && $goal->user_id === $user->id
            && $goal->enrollment?->status === EnrollmentStatus::Learning;
    }
}
