<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 個人学習目標を表すModel。受講登録(Enrollment)配下に個人目標を立る。
 * 受講生本人のみが目標を CRUD でき、達成 / 未達成の視覚的区別を伴う一覧を受講登録詳細画面に表示する。
 * 担当コーチ / 管理者は受講生の自主的な目標を閲覧でき(介入はせず閲覧のみ)、面談や chat での声かけの参考材料にする。
 *
 * 関連: User(受講生) / Enrollment
 */
class EnrollmentGoal extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'title',
        'target_date',
        'description',
        'achieved_at',
    ];

    protected $casts = [
        'target_date' => 'datetime',
        'achieved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

     /**
     * 個人目標の並び順 (未達成→達成、期日順。同期日の場合は作成順)
     *
     * @param Builder<EnrollmentGoal> $query
     * @return Builder<EnrollmentGoal>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('achieved_at IS NULL desc')
            ->orderBy('target_date')
            ->orderBy('created_at');
    }
}
