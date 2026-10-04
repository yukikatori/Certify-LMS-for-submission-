<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class PasswordUpdateAction
{
    /**
     * @param array{password: string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        return DB::transaction(function () use ($user, $validated) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);

            return $user->fresh();
        });
    }
}
