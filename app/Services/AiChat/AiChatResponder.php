<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Enums\AiChatMessageRole;
use App\Models\AiChatConversation;

/**
 * 会話データを Gemini API 用のリクエスト payload に変換して、GeminiClient に渡すサービス
 */
final class AiChatResponder
{
    public function __construct(
        private readonly GeminiClient $client,
    ) {}

    public function respond(AiChatConversation $conversation): GeminiResponse
    {
        $conversation->loadMissing([
            'messages',
            'enrollment.certification',
            'section.chapter.part.certification',
        ]);

        return $this->client->generate([
            'systemInstruction' => [
                'parts' => [[
                    'text' => $this->systemPrompt($conversation),
                ]],
            ],
            'contents' => $this->contents($conversation),
            'generationConfig' => [
                'temperature' => 0.4,
            ],
        ]);
    }

    private function systemPrompt(AiChatConversation $conversation): string
    {
        $certification = $conversation->enrollment?->certification?->name;
        $section = $conversation->section;

        return trim(sprintf(
            "あなたは資格学習を支援するAI相談役です。\n".
            "受講生に答えを丸投げせず、理解を助ける説明をしてください。\n".
            "対象資格: %s\n".
            "閲覧中Section: %s\n",
            $certification ?: '未指定',
            $section?->title ?: '未指定',
        ));
    }

    private function contents(AiChatConversation $conversation): array
    {
        return $conversation->messages
            ->take(-10)
            ->map(function ($message) {
                return [
                    'role' => $message->role === AiChatMessageRole::Assistant ? 'model' : 'user',
                    'parts' => [[
                        'text' => $message->content,
                    ]],
                ];
            })
            ->values()
            ->all();
    }

    public function generateTitle(AiChatConversation $conversation): ?string
    {
        $conversation->loadMissing([
            'messages',
            'enrollment.certification',
            'section',
        ]);

        $response = $this->client->generate([
            'systemInstruction' => [
                'parts' => [[
                    'text' => 'あなたは学習相談チャットのタイトル作成係です。会話内容をもとに、受講生が後から探しやすい日本語タイトルを作ってください。',
                ]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $this->titlePrompt($conversation),
                ]],
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ]);

        $title = trim($response->text);
        $title = trim($title, " \t\n\r\0\x0B\"'");
        $title = preg_replace('/\A[「『]+|[」』]+\z/u', '', $title) ?? '';

        if ($title === '') {
            return null;
        }

        return mb_substr($title, 0, 100, 'UTF-8');
    }

    private function titlePrompt(AiChatConversation $conversation): string
    {
        $certification = $conversation->enrollment?->certification?->name ?? '未指定';
        $section = $conversation->section?->title ?? '未指定';

        $messages = $conversation->messages
            ->take(-6)
            ->map(function ($message) {
                $role = $message->role === AiChatMessageRole::Assistant ? 'AI' : '受講生';

                return $role.': '.$message->content;
            })
            ->implode("\n");

        return trim(sprintf(
            "対象資格: %s\n".
            "Section: %s\n".
            "会話:\n%s\n\n".
            "条件:\n".
            "- 100文字以内\n".
            "- 会話内容がわかる具体的なタイトル\n".
            '- タイトルのみを返す',
            $certification,
            $section,
            $messages,
        ));
    }
}
