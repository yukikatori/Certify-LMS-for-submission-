<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 追加面談パック購入時の決済控え。
 *
 * amount / quantity は購入時点の値を保存し、面談パックマスタ変更後も監査できるようにする。
 */
class Payment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'meeting_pack_id',
        'amount',
        'currency',
        'quantity',
        'status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
        'failed_at',
        'quota_granted_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'quantity' => 'integer',
        'status' => PaymentStatus::class,
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'quota_granted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MeetingPack, $this>
     */
    public function meetingPack(): BelongsTo
    {
        return $this->belongsTo(MeetingPack::class);
    }
}
