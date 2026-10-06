<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\GeminiApiKeyNotConfiguredException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\AiChat\AiChatResponder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gemini AI チャットボットの回答を作成するユースケース。
 * 受講生とGeminiの回答を作成する。Geminiの通信失敗時でも回答を保持してエラーメッセージを返す。
 */
final class StoreMessageAction
{
    public function __construct(
        private readonly AiChatResponder $responder,
    ) {}

    /**
     * @param array{content: string} $validated
     *
     * @return array{user_message: array<string, mixed>, assistant_message: array<string, mixed>}
     */
    public function __invoke(User $user, AiChatConversation $conversation, array $validated): array
    {
        $this->assertDailyLimit($user);

        $userMessage = DB::transaction(function () use ($conversation, $validated) {
            $message = $conversation->messages()->create([
                'role' => AiChatMessageRole::User,
                'content' => $validated['content'],
                'status' => AiChatMessageStatus::Completed,
            ]);

            $conversation->forceFill([
                'last_message_at' => now(),
            ])->save();

            return $message;
        });

        try {
            $conversation->load([
                'messages',
                'enrollment.certification',
                'section.chapter.part.certification',
            ]);

            $response = $this->responder->respond($conversation);

            $assistantMessage = DB::transaction(function () use ($conversation, $response) {
                $message = $conversation->messages()->create([
                    'role' => AiChatMessageRole::Assistant,
                    'content' => $response->text,
                    'status' => AiChatMessageStatus::Completed,
                    'model' => $response->model,
                    'prompt_tokens' => $response->promptTokens,
                    'output_tokens' => $response->completionTokens,
                    'total_tokens' => $response->totalTokens,
                    'response_time_ms' => $response->latencyMs,
                ]);

                $conversation->forceFill([
                    'last_message_at' => now(),
                ])->save();

                return $message;
            });

            $this->updateAutoTitle($conversation);

        } catch (Throwable $e) {
            $assistantMessage = DB::transaction(function () use ($conversation, $e) {
                $message = $conversation->messages()->create([
                    'role' => AiChatMessageRole::Assistant,
                    'content' => $this->errorContent($e),
                    'status' => AiChatMessageStatus::Error,
                    'error_detail' => $e->getMessage(),
                    'model' => config('ai-chat.gemini.model'),
                ]);

                $conversation->forceFill([
                    'last_message_at' => now(),
                ])->save();

                return $message;
            });
        }

        return [
            'user_message' => $this->serializeMessage($userMessage),
            'assistant_message' => $this->serializeMessage($assistantMessage),
        ];
    }

    private function assertDailyLimit(User $user): void
    {
        $limit = (int) config('ai-chat.daily_message_limit', 50);

        if ($limit <= 0) {
            abort(429);
        }

        $sentCount = AiChatMessage::query()
            ->where('role', AiChatMessageRole::User->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $user->id))
            ->count();

        abort_if($sentCount >= $limit, 429);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(AiChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role->value,
            'content' => $message->content,
            'status' => $message->status->value,
            'model' => $message->model,
            'response_time_ms' => $message->response_time_ms,
            'output_tokens' => $message->output_tokens,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    private function errorContent(Throwable $e): string
    {
        if ($e instanceof GeminiApiKeyNotConfiguredException) {
            return 'Gemini API キーが未設定のため、AI 相談は現在利用できません。管理者に設定を確認してください。';
        }

        return 'AI が応答できませんでした。しばらく時間をおいて再試行してください。';
    }

    private function updateAutoTitle(AiChatConversation $conversation): void
    {
        if (! $conversation->auto_title_enabled) {
            return;
        }

        try {
            $title = $this->responder->generateTitle($conversation);

            if ($title === null) {
                return;
            }

            $conversation->forceFill([
                'title' => $title,
            ])->save();
        } catch (Throwable) {
            // タイトル生成失敗は、AI回答自体の成功を壊さない
        }
    }
}
