<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gemini AI チャットボットの会話を作成するユースケース。
 */
final class StoreAction
{
    public function __construct(
        private readonly StoreMessageAction $storeMessage,
    ) {}

    /**
     * @param array{source: string, message?: string|null, section_id?: string|null} $validated
     */
    public function __invoke(User $user, array $validated): AiChatConversation
    {
        $initialMessage = trim((string) ($validated['message'] ?? ''));

        $conversation = DB::transaction(function () use ($user, $validated, $initialMessage) {
            $section = $this->resolveSection($validated['section_id'] ?? null);
            $enrollmentId = $this->resolveEnrollmentId($user, $section);

            abort_if($section !== null && $enrollmentId === null, 403);

            $conversation = $this->resolveConversation($user, $section);

            if ($conversation === null) {
                $conversation = AiChatConversation::create([
                    'user_id' => $user->id,
                    'enrollment_id' => $enrollmentId,
                    'section_id' => $section?->id,
                    'title' => $this->initialTitle($section, $initialMessage),
                    'auto_title_enabled' => true,
                    'last_message_at' => now(),
                ]);
            }

            return $conversation;
        });

        if ($initialMessage !== '') {
            ($this->storeMessage)($user, $conversation, [
                'content' => $initialMessage,
            ]);

            $conversation->refresh();
        }

        return $conversation->load([
            'messages',
            'enrollment.certification',
            'section',
        ]);
    }

    private function resolveSection(?string $sectionId): ?Section
    {
        if ($sectionId === null || $sectionId === '') {
            return null;
        }

        return Section::query()
            ->with('chapter.part.certification')
            ->findOrFail($sectionId);
    }

    private function resolveEnrollmentId(User $user, ?Section $section): ?string
    {
        if ($section !== null) {
            $certificationId = $section->chapter?->part?->certification_id;

            if ($certificationId !== null) {
                return $user->enrollments()
                    ->where('certification_id', $certificationId)
                    ->where('status', EnrollmentStatus::Learning->value)
                    ->value('id');
            }
        }

        return $user->defaultEnrollment?->id;
    }

    private function resolveConversation(User $user, ?Section $section): ?AiChatConversation
    {
        if ($section === null) {
            return null;
        }

        return $user->aiChatConversations()
            ->where('section_id', $section->id)
            ->first();
    }

    private function initialTitle(?Section $section, string $initialMessage): string
    {
        if ($initialMessage !== '') {
            return Str::limit($initialMessage, 40);
        }

        if ($section?->title) {
            return $section->title.'の相談';
        }

        return '新しい相談';
    }
}
