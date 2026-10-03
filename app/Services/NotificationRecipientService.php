<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

final class NotificationRecipientService
{
    public function canReceive(User $user): bool
    {
        if (! in_array($user->role, [UserRole::Student, UserRole::Coach], true)) {
            return false;
        }

        return $user->status === UserStatus::InProgress && $user->deleted_at === null;
    }
}
