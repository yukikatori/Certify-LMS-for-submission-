<?php

declare(strict_types=1);

namespace App\UseCases\Chat;

use App\Events\ChatMessageSent;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\Services\NotificationRecipientService;
use Illuminate\Support\Facades\DB;

/**
 * ChatRoom にメッセージを INSERT し、送信者の既読時刻を更新したうえで Broadcast を発火する Action。
 *
 * - INSERT 後、ChatMessage::booted() が `chat_rooms.last_message_at` を denormalize 更新する
 * - 送信者自身の `ChatMember.last_read_at = now()` を UPDATE(自分のメッセージは未読としてカウントしない)
 * - 通信失敗が DB 整合性に波及しないよう Pusher Broadcast は `DB::afterCommit()` で送る
 * - 担当コーチ未割当の判定は Controller 側で実施済(`CertificationCoachNotAssignedForChatException` 振り分け)
 */
final class StoreMessageAction
{
    public function __construct(
        private readonly NotificationRecipientService $notificationRecipients,
    ) {}

    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $sender, ChatRoom $room, array $validated): ChatMessage
    {
        return DB::transaction(function () use ($sender, $room, $validated) {
            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                'sender_user_id' => $sender->id,
                'body' => $validated['body'],
            ]);

            ChatMember::query()
                ->where('chat_room_id', $room->id)
                ->where('user_id', $sender->id)
                ->update(['last_read_at' => now()]);

            DB::afterCommit(function () use ($message): void {
                $message->loadMissing([
                    'sender',
                    'chatRoom.enrollment.certification',
                    'chatRoom.members.user',
                ]);

                $this->notifyChatRecipients($message);

                broadcast(new ChatMessageSent($message->load('sender')))->toOthers();
            });

            return $message;
        });
    }

    private function notifyChatRecipients(ChatMessage $message): void
    {
        $room = $message->chatRoom;
        $certificationName = $room->enrollment?->certification?->name ?? '受講資格';

        foreach ($room->members as $member) {
            $recipient = $member->user;

            if ($recipient === null) {
                continue;
            }

            if ((string) $recipient->id === (string) $message->sender_user_id) {
                continue;
            }

            if (! $this->notificationRecipients->canReceive($recipient)) {
                continue;
            }

            $recipient->notify(new BusinessEventNotification([
                'notification_type' => 'chat_message_received',
                'title' => 'chat に新着メッセージがあります',
                'message' => $message->sender->name.'さんから「'.$certificationName.'」のchatにメッセージが届きました。',
                'body_preview' => mb_strimwidth($message->body, 0, 120, '...'),
                'action_url' => route('chat.show', $room),
                'related_type' => 'chat_message',
                'related_id' => (string) $message->id,
            ]));
        }
    }
}
