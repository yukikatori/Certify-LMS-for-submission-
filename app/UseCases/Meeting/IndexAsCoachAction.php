<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * コーチ宛の面談一覧を取得するユースケース。
 * 担当受講生 / 受講登録での絞り込みを併せて提供する。
 */
final class IndexAsCoachAction
{
    public function __invoke(
        User $viewer,
        string $filter,
        ?string $studentId,
        ?string $enrollmentId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Meeting::query()
            ->with(['enrollment.certification', 'student'])
            ->forCoach($viewer)
            ->when($studentId, fn ($q, $id) => $q->where('student_id', $id))
            ->when($enrollmentId, fn ($q, $id) => $q->where('enrollment_id', $id));

        // upcoming: 次の面談を一番上に置く (昇順) / past + all: 直近の活動を一番上 (降順)
        $meetings = match ($filter) {
            'past' => $query->past()->orderByDesc('scheduled_at')->paginate($perPage),
            'all' => $query->orderByDesc('scheduled_at')->paginate($perPage),
            default => $query->upcoming()->orderBy('scheduled_at')->paginate($perPage),
        };

        return $meetings;
    }
}
