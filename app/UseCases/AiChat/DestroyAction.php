<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;
use Illuminate\Support\Facades\DB;

/**
 * Gemini AI チャットボットの会話を削除するユースケース。
 */
final class DestroyAction
{
    public function __invoke(AiChatConversation $conversation): void
    {
        DB::transaction(fn () => $conversation->delete());
    }
}
