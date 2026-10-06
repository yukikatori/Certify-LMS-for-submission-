<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;
use Illuminate\Support\Facades\DB;

/**
 * Gemini AI チャットボットの会話のタイトルを更新するユースケース。
 */
final class UpdateAction
{
    /**
     * @param array{title: string} $validated
     */
    public function __invoke(AiChatConversation $conversation, array $validated): AiChatConversation
    {
        return DB::transaction(function () use ($conversation, $validated) {
            $conversation->update([
                'title' => $validated['title'],
                'auto_title_enabled' => false,
            ]);

            return $conversation;
        });
    }
}
