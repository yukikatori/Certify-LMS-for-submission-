<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QaThreadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaThread extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'certification_id',
        'title',
        'body',
        'status',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
    ];

    /**
     * 質問掲示板：投稿者
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 質問掲示板：投稿に紐づく資格
     *
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'certification_id');
    }

    /**
     * 質問掲示板：投稿に紐づく返信
     *
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class, 'qa_thread_id');
    }
}
