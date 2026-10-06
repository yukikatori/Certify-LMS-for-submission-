<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;

/**
 * Gemini AI チャットボットの会話を取得するユースケース。
 * 会話が存在すればコントローラでその詳細へリダイレクトする。
 */
final class IndexAction
{
    public function __invoke(User $user): ?AiChatConversation
    {
        return $user->aiChatConversations()
            ->with(['messages', 'enrollment.certification', 'section'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
