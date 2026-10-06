<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\AiChatConversation;
use App\Models\User;
use App\Policies\AiChatConversationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AiChatConversationPolicy の判定を検証する Unit テスト。
 * view / update / delete / sendMessage は会話オーナーのみ許可する。
 */
class AiChatConversationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_operate_conversation(): void
    {
        $owner = User::factory()->student()->create();
        $conversation = $this->createConversation($owner);
        $policy = new AiChatConversationPolicy;

        $this->assertTrue($policy->view($owner, $conversation));
        $this->assertTrue($policy->update($owner, $conversation));
        $this->assertTrue($policy->delete($owner, $conversation));
        $this->assertTrue($policy->sendMessage($owner, $conversation));
    }

    public function test_other_student_cannot_operate_conversation(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $conversation = $this->createConversation($owner);
        $policy = new AiChatConversationPolicy;

        $this->assertFalse($policy->view($other, $conversation));
        $this->assertFalse($policy->update($other, $conversation));
        $this->assertFalse($policy->delete($other, $conversation));
        $this->assertFalse($policy->sendMessage($other, $conversation));
    }

    public function test_staff_cannot_operate_student_conversation(): void
    {
        $owner = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $conversation = $this->createConversation($owner);
        $policy = new AiChatConversationPolicy;

        foreach ([$admin, $coach] as $staff) {
            $this->assertFalse($policy->view($staff, $conversation));
            $this->assertFalse($policy->update($staff, $conversation));
            $this->assertFalse($policy->delete($staff, $conversation));
            $this->assertFalse($policy->sendMessage($staff, $conversation));
        }
    }

    private function createConversation(User $owner): AiChatConversation
    {
        return AiChatConversation::create([
            'user_id' => $owner->id,
            'title' => 'AI 相談',
            'auto_title_enabled' => true,
            'last_message_at' => now(),
        ]);
    }
}
