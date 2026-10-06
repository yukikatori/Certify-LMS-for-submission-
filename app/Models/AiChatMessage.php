<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gemini AI チャットのやり取りのModel。資格や教材に紐づけて質問を投稿することができる。
 * 関連: AiChatConversation
 */
class AiChatMessage extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'status',
        'error_detail',
        'model',
        'prompt_tokens',
        'output_tokens',
        'total_tokens',
        'response_time_ms',
    ];

    protected $casts = [
        'role' => AiChatMessageRole::class,
        'status' => AiChatMessageStatus::class,
    ];

    /**
     * @return BelongsTo<AiChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }
}
