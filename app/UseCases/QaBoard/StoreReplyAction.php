<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\Services\NotificationRecipientService;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板の質問への回答を新規作成するユースケース。
 */
final class StoreReplyAction
{
    public function __construct(
        private readonly NotificationRecipientService $notificationRecipients,
    ) {}

    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $user, QaThread $thread, array $validated): QaReply
    {
        return DB::transaction(function () use ($user, $thread, $validated) {
            $reply = $thread->replies()->create([
                'user_id' => $user->id,
                'body' => $validated['body'],
            ]);

            DB::afterCommit(function () use ($reply): void {
                $reply->loadMissing([
                    'user',
                    'thread.user',
                    'thread.certification.coaches',
                ]);

                $this->notifyQaReplyRecipients($reply);
            });

            return $reply;
        });
    }

    private function notifyQaReplyRecipients(QaReply $reply): void
    {
        $thread = $reply->thread;

        $recipients = collect([$thread->user])
            ->merge($thread->certification?->coaches ?? collect())
            ->unique('id');

        foreach ($recipients as $recipient) {
            if ($recipient === null) {
                continue;
            }

            if ((string) $recipient->id === (string) $reply->user_id) {
                continue;
            }

            if (! $this->notificationRecipients->canReceive($recipient)) {
                continue;
            }

            $recipient->notify(new BusinessEventNotification([
                'notification_type' => 'qa_reply_received',
                'title' => '質問掲示板に返信があります',
                'message' => $reply->user->name.'さんが「'.$thread->title.'」に返信しました。',
                'body_preview' => mb_strimwidth($reply->body, 0, 120, '...'),
                'action_url' => route('qa-board.show', $thread),
                'related_type' => 'qa_reply',
                'related_id' => (string) $reply->id,
            ]));
        }
    }
}
