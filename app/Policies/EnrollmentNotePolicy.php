<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * 受講生メモの認可ルール。
 */
class EnrollmentNotePolicy
{
    public function viewAny(User $auth, Enrollment $enrollment): bool
    {
        if ($enrollment->trashed()) {
            return false;
        }

        $enrollment->loadMissing('certification.coaches');

        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $enrollment->certification?->coaches->contains('id', $auth->id) ?? false,
            UserRole::Student => false,
        };
    }

    public function create(User $auth, Enrollment $enrollment): bool
    {
        if ($enrollment->trashed()) {
            return false;
        }

        $enrollment->loadMissing('certification.coaches');

        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $enrollment->certification?->coaches->contains('id', $auth->id) ?? false,
            UserRole::Student => false,
        };
    }

    public function update(User $auth, EnrollmentNote $note): bool
    {
        $enrollment = $note->enrollment;
        if (! $enrollment instanceof Enrollment || $enrollment->trashed()) {
            return false;
        }

        $enrollment->loadMissing('certification.coaches');

        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => ($enrollment->certification?->coaches->contains('id', $auth->id) ?? false)
                && $note->user_id === $auth->id,
            UserRole::Student => false,
        };
    }

    public function delete(User $auth, EnrollmentNote $note): bool
    {
        $enrollment = $note->enrollment;
        if (! $enrollment instanceof Enrollment || $enrollment->trashed()) {
            return false;
        }

        $enrollment->loadMissing('certification.coaches');

        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => ($enrollment->certification?->coaches->contains('id', $auth->id) ?? false)
                && $note->user_id === $auth->id,
            UserRole::Student => false,
        };
    }
}
