<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaBoard\StoreReplyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreReplyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_database_notification_to_thread_author_and_assigned_coaches_except_replier(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $replier = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        $this->attachCoach($certification, $replier, $admin);
        $thread = QaThread::create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => '過去問の復習方法について',
            'body' => 'どの順番で復習すればよいですか？',
            'status' => QaThreadStatus::Unresolved->value,
        ]);

        $reply = app(StoreReplyAction::class)($replier, $thread, [
            'body' => '間違えた分野から優先して復習しましょう。',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $author->getMorphClass(),
            'notifiable_id' => $author->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $coach->getMorphClass(),
            'notifiable_id' => $coach->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => $replier->getMorphClass(),
            'notifiable_id' => $replier->id,
        ]);

        foreach ([$author, $coach] as $recipient) {
            $notification = $recipient->notifications()->first();

            $this->assertNotNull($notification);
            $this->assertSame('qa_reply_received', $notification->data['notification_type']);
            $this->assertSame('qa_reply', $notification->data['related_type']);
            $this->assertSame((string) $reply->id, $notification->data['related_id']);
            $this->assertSame(route('qa-board.show', $thread), $notification->data['action_url']);
        }
    }

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }
}
