<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gemini AI チャットのModel。資格や教材に紐づけて質問を投稿することができる。
 * 会話には内容に応じて AI が自動で見出しを付ける(無効化スイッチ、編集機能あり)。
 * 会話のやり取りはAiChatMessage内にて管理する。
 *
 * 関連: User / Enrollment / Section / AiChatMessage
 */
class AiChatConversation extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'section_id',
        'title',
        'auto_title_enabled',
        'last_message_at',
    ];

    protected $casts = [
        'auto_title_enabled' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    /**
     * @return HasMany<AiChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'conversation_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
