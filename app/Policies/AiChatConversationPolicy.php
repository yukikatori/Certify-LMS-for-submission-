<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiChatConversation;
use App\Models\User;

/**
 * Gemini AI チャットボットの認可ルール。
 * 会話の 詳細表示 / 削除 や 見出しの編集、メッセージの送信は会話のオーナーのみ可。
 */
class AiChatConversationPolicy
{
    public function view(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function sendMessage(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }
}
