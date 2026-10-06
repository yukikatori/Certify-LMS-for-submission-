<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

/**
 * Gemini AI チャットボットの会話詳細を取得するユースケース。
 */
final class ShowAction
{
    public function __invoke(AiChatConversation $conversation): AiChatConversation
    {
        return $conversation
            ->load([
                'messages',
                'enrollment.certification',
                'section',
            ]);
    }
}
