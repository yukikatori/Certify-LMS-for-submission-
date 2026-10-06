<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Http\Requests\AiChat\StoreRequest;
use App\Http\Requests\AiChat\UpdateRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\DestroyAction;
use App\UseCases\AiChat\IndexAction;
use App\UseCases\AiChat\ShowAction;
use App\UseCases\AiChat\StoreAction;
use App\UseCases\AiChat\StoreMessageAction;
use App\UseCases\AiChat\UpdateAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gemini AI チャットボット用 Controller。
 */
class AiChatController extends Controller
{
    public function index(Request $request, IndexAction $action): View|RedirectResponse
    {
        $conversation = $action($request->user());

        if ($conversation !== null) {
            $request->session()->reflash();

            return redirect()->route('ai-chat.conversations.show', $conversation);
        }

        return view('ai-chat.empty-state');
    }

    public function show(Request $request, AiChatConversation $conversation, ShowAction $action): View|JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation = $action($conversation);

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ],
                'messages' => $conversation->messages->map(fn ($message): array => [
                    'id' => $message->id,
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'status' => $message->status->value,
                    'created_at' => $message->created_at?->toISOString(),
                ])->values(),
            ]);
        }

        return view('ai-chat.show', [
            'conversation' => $conversation,
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $conversation = $action($request->user(), $validated);

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ],
            ], 201);
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    public function update(UpdateRequest $request, AiChatConversation $conversation, UpdateAction $action): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validated();
        $conversation = $action($conversation, $validated);

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(AiChatConversation $conversation, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $action($conversation);

        return redirect()
            ->route('ai-chat.index')
            ->with('success', '会話を削除しました。');
    }

    public function storeMessage(
        StoreMessageRequest $request,
        AiChatConversation $conversation,
        StoreMessageAction $action
    ): JsonResponse {
        $this->authorize('sendMessage', $conversation);

        $result = $action($request->user(), $conversation, $request->validated());

        return response()->json([
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
            'conversation' => [
                'id' => $conversation->fresh()->id,
                'title' => $conversation->fresh()->title,
            ],
        ]);
    }
}
