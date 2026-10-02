<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板の質問を新規作成するユースケース。
 */
final class StoreAction
{
    /**
     * @param array{certification_id: string, title: string, body: string} $validated
     */
    public function __invoke(User $student, array $validated): QaThread
    {
        return DB::transaction(fn () => QaThread::create([
            'user_id' => $student->id,
            'certification_id' => $validated['certification_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => 'unresolved',
        ]));
    }
}
