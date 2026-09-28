<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingQuotaService;

/**
 * 受講生本人の面談一覧を取得するユースケース。
 * filter (upcoming/past/all) クエリで履歴を切り替える。
 */
final class IndexAction
{
    public function __construct(
        private MeetingQuotaService $meetingQuota,
    ) {}

    public function __invoke(
        User $viewer,
        string $filter,
        int $perPage = 20,
    ): array {
        $query = Meeting::query()
            ->with(['enrollment.certification', 'coach'])
            ->forStudent($viewer)
            ->orderByDesc('scheduled_at');

        $meetings = match ($filter) {
            'past' => $query->past()->paginate($perPage),
            'all' => $query->paginate($perPage),
            default => $query->upcoming()->paginate($perPage),
        };

        return [
            'meetings' => $meetings,
            'remaining' => $this->meetingQuota->remaining($viewer),
        ];
    }
}
