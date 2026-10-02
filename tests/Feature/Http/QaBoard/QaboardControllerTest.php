<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QaboardControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * for index method
     */
    public function test_student_index_lists_published_certification_threads_with_empty_filters(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $ownThread = QaThread::factory()->forStudent($student)->forCertification($certification)->create();
        $otherThread = QaThread::factory()->forStudent($otherStudent)->forCertification($certification)->create();
        $draftThread = QaThread::factory()->forStudent($student)->forCertification($draftCertification)->create();

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');
        $response->assertViewHas('threads', fn ($threads) => $threads->contains('id', $ownThread->id)
            && $threads->contains('id', $otherThread->id)
            && ! $threads->contains('id', $draftThread->id)
        );
    }

    public function test_index_filters_threads_by_status(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $resolvedThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->resolved()
            ->create();

        $unresolvedThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->unresolved()
            ->create();

        $response = $this->actingAs($student)->get(route('qa-board.index', [
            'status' => QaThreadStatus::Resolved->value,
        ]));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');
        $response->assertViewHas('threads', fn ($threads) => $threads->contains('id', $resolvedThread->id)
            && ! $threads->contains('id', $unresolvedThread->id)
        );
    }

    public function test_index_filters_threads_by_certification(): void
    {
        $student = User::factory()->student()->create();
        $targetCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($targetCertification)
            ->create();

        $otherThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($otherCertification)
            ->create();

        $response = $this->actingAs($student)->get(route('qa-board.index', [
            'certification_id' => $targetCertification->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');
        $response->assertViewHas('threads', fn ($threads) => $threads->contains('id', $targetThread->id)
            && ! $threads->contains('id', $otherThread->id)
        );
    }

    public function test_index_filters_threads_by_keyword(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create([
                'title' => 'target',
                'body' => 'target',
            ]);

        $otherThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create([
                'title' => 'other',
                'body' => 'other',
            ]);

        $response = $this->actingAs($student)->get(route('qa-board.index', [
            'keyword' => 'target',
        ]));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');
        $response->assertViewHas('threads', fn ($threads) => $threads->contains('id', $targetThread->id)
            && ! $threads->contains('id', $otherThread->id)
        );
    }

    public function test_index_orders_threads_by_status_then_latest_updated(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $newResolvedThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->resolved()
            ->create([
                'updated_at' => now()->subDay(),
            ]);

        $oldResolvedThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->resolved()
            ->create([
                'updated_at' => now()->subDays(2),
            ]);

        $newUnresolvedThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->unresolved()
            ->create([
                'updated_at' => now()->subDay(),
            ]);

        $oldUnresolvedThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->unresolved()
            ->create([
                'updated_at' => now()->subDays(2),
            ]);

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');

        $response->assertViewHas('threads', function ($threads) use (
            $newResolvedThread,
            $oldResolvedThread,
            $newUnresolvedThread,
            $oldUnresolvedThread,
        ) {
            $this->assertSame([
                $newUnresolvedThread->id,
                $oldUnresolvedThread->id,
                $newResolvedThread->id,
                $oldResolvedThread->id,
            ], $threads->getCollection()->pluck('id')->all());

            return true;
        });
    }

    public function test_coach_index_lists_only_assigned_certification_threads(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $assignedCertification = Certification::factory()->published()->create();
        $unassignedCertification = Certification::factory()->published()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $assignedCertification->id,
            'user_id' => $coach->id,
        ]);

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($assignedCertification)
            ->create();

        $otherThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($unassignedCertification)
            ->create();

        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');
        $response->assertViewHas('threads', fn ($threads) => $threads->contains('id', $targetThread->id)
            && ! $threads->contains('id', $otherThread->id)
        );
    }

    /**
     * for show method
     */
    public function test_student_can_show_published_certification_thread_with_replies(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $targetReply = QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($otherStudent)
            ->create();

        $response = $this->actingAs($student)->get(route('qa-board.show', $targetThread));

        $response->assertOk();
        $response->assertViewIs('qa-thread.show');

        $response->assertViewHas('thread', fn ($thread) => $thread->id === $targetThread->id
        );

        $response->assertViewHas('replies', fn ($replies) => $replies->contains('id', $targetReply->id)
        );
    }

    public function test_coach_can_show_assigned_certification_thread_with_replies(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $assignedCertification = Certification::factory()->published()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $assignedCertification->id,
            'user_id' => $coach->id,
        ]);

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($assignedCertification)
            ->create();

        $targetReply = QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($otherStudent)
            ->create();

        $response = $this->actingAs($coach)->get(route('qa-board.show', $targetThread));

        $response->assertOk();
        $response->assertViewIs('qa-thread.show');

        $response->assertViewHas('thread', fn ($thread) => $thread->id === $targetThread->id
        );

        $response->assertViewHas('replies', fn ($replies) => $replies->contains('id', $targetReply->id)
        );
    }

    public function test_coach_cannot_show_unassigned_certification_thread(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $unassignedCertification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($unassignedCertification)
            ->create();

        $response = $this->actingAs($coach)->get(route('qa-board.show', $targetThread));

        $response->assertForbidden();
    }

    /**
     * for create method
     */
    public function test_student_can_access_create_page_with_published_certifications(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();

        $response = $this->actingAs($student)->get(route('qa-board.create'));

        $response->assertOk();
        $response->assertViewIs('qa-thread.create');
        $response->assertViewHas('certifications', fn ($certifications) => $certifications->contains('id', $certification->id)
            && ! $certifications->contains('id', $draftCertification->id)
        );
    }

    public function test_coach_cannot_access_create_page(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->get(route('qa-board.create'));

        $response->assertForbidden();
    }

    /**
     * for store method
     */
    public function test_student_can_store_thread_with_published_certification_and_redirect_to_show_page(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'content',
        ]);

        $thread = QaThread::query()->firstOrFail();

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'content',
            'status' => QaThreadStatus::Unresolved->value,
        ]);
    }

    public function test_coach_cannot_store_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($coach)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'content',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_threads', [
            'user_id' => $coach->id,
            'certification_id' => $certification->id,
            'title' => 'test',
            'body' => 'content',
        ]);
    }

    /**
     * for edit method
     */
    public function test_thread_author_can_access_edit_page(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($student)->get(route('qa-board.edit', $targetThread));

        $response->assertOk();
        $response->assertViewIs('qa-thread.edit');

        $response->assertViewHas('thread', fn ($thread) => $thread->id === $targetThread->id
        );
    }

    public function test_non_author_cannot_access_edit_page(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($otherStudent)->get(route('qa-board.edit', $targetThread));

        $response->assertForbidden();
    }

    /**
     * for update method
     */
    public function test_thread_author_can_update_own_thread_and_redirect_to_show_page(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($student)->patch(route('qa-board.update', $targetThread), [
            'title' => 'modified',
            'body' => 'modified',
        ]);

        $response->assertRedirect(route('qa-board.show', $targetThread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $targetThread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => 'modified',
            'body' => 'modified',
        ]);
    }

    public function test_non_author_cannot_update_thread(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($otherStudent)->patch(route('qa-board.update', $targetThread), [
            'title' => 'modified',
            'body' => 'modified',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $targetThread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => 'modified',
            'body' => 'modified',
        ]);
    }

    /**
     * for delete method
     */
    public function test_thread_author_can_delete_own_thread_and_redirect_to_index_page(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($student)->delete(route('qa-board.destroy', $targetThread));

        $response->assertRedirect(route('qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $targetThread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => $targetThread->title,
            'body' => $targetThread->body,
        ]);
    }

    public function test_non_author_cannot_delete_thread(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($otherStudent)->delete(route('qa-board.destroy', $targetThread));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $targetThread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => $targetThread->title,
            'body' => $targetThread->body,
        ]);
    }

    public function test_thread_author_cannot_delete_thread_with_replies(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($otherStudent)
            ->create();

        $response = $this->actingAs($student)->delete(route('qa-board.destroy', $targetThread));

        $response->assertRedirect();
        $response->assertSessionHas('error', '回答がついているスレッドは削除できません。');
    }

    /**
     * for resolve method
     */
    public function test_thread_author_can_resolve_unresolved_thread_and_redirect_to_show_page(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->unresolved()
            ->create();

        $response = $this->actingAs($student)->post(route('qa-board.resolve', $targetThread));

        $response->assertRedirect(route('qa-board.show', $targetThread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $targetThread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => $targetThread->title,
            'body' => $targetThread->body,
            'status' => QaThreadStatus::Resolved->value,
        ]);
    }

    public function test_non_author_cannot_resolve_thread(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->unresolved()
            ->create();

        $response = $this->actingAs($otherStudent)->post(route('qa-board.resolve', $targetThread));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $targetThread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => $targetThread->title,
            'body' => $targetThread->body,
            'status' => QaThreadStatus::Unresolved->value,
        ]);
    }

    /**
     * for unresolve method
     */
    public function test_thread_author_can_unresolve_resolved_thread_and_redirect_to_show_page(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->resolved()
            ->create();

        $response = $this->actingAs($student)->post(route('qa-board.unresolve', $targetThread));

        $response->assertRedirect(route('qa-board.show', $targetThread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $targetThread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => $targetThread->title,
            'body' => $targetThread->body,
            'status' => QaThreadStatus::Unresolved->value,
        ]);
    }

    public function test_non_author_cannot_unresolve_thread(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->resolved()
            ->create();

        $response = $this->actingAs($otherStudent)->post(route('qa-board.unresolve', $targetThread));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $targetThread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => $targetThread->title,
            'body' => $targetThread->body,
            'status' => QaThreadStatus::Resolved->value,
        ]);
    }

    /**
     * for storeReply method
     */
    public function test_student_can_store_reply_to_published_certification_thread_and_redirect_to_show_page(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($otherStudent)
            ->post(route('qa-board.replies.store', $targetThread), [
                'body' => 'reply',
            ]);

        $response->assertRedirect(route('qa-board.show', $targetThread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $targetThread->id,
            'user_id' => $otherStudent->id,
            'body' => 'reply',
        ]);
    }

    public function test_assigned_coach_can_store_reply_to_published_certification_thread_and_redirect_to_show_page(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $targetThread), [
                'body' => 'reply',
            ]);

        $response->assertRedirect(route('qa-board.show', $targetThread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $targetThread->id,
            'user_id' => $coach->id,
            'body' => 'reply',
        ]);
    }

    public function test_unassigned_coach_cannot_store_reply(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $response = $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $targetThread), [
                'body' => 'reply',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $targetThread->id,
            'user_id' => $coach->id,
            'body' => 'reply',
        ]);
    }

    /**
     * for editReply method
     */
    public function test_reply_author_can_access_reply_edit_page(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $targetReply = QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($otherStudent)
            ->create();

        $response = $this->actingAs($otherStudent)
            ->get(route('qa-board.replies.edit', ['thread' => $targetThread, 'reply' => $targetReply]));

        $response->assertOk();
        $response->assertViewIs('qa-thread.reply-edit');

        $response->assertViewHas('thread', fn ($thread) => $thread->id === $targetThread->id
        );

        $response->assertViewHas('reply', fn ($reply) => $reply->id === $targetReply->id
        );
    }

    public function test_non_author_cannot_access_reply_edit_page(): void
    {
        $student = User::factory()->student()->create();
        $authorStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $targetReply = QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($authorStudent)
            ->create();

        $response = $this->actingAs($student)
            ->get(route('qa-board.replies.edit', ['thread' => $targetThread, 'reply' => $targetReply]));

        $response->assertForbidden();
    }

    public function test_cannot_access_reply_edit_page_when_reply_does_not_belong_to_thread(): void
    {
        $student = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $otherThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forStudent($replyAuthor)
            ->create();

        $response = $this->actingAs($replyAuthor)
            ->get(route('qa-board.replies.edit', [
                'thread' => $otherThread,
                'reply' => $reply,
            ]));

        $response->assertNotFound();
    }

    /**
     * for updateReply method
     */
    public function test_reply_author_can_update_own_reply_and_redirect_to_show_page(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $targetReply = QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($otherStudent)
            ->create();

        $response = $this->actingAs($otherStudent)
            ->patch(route('qa-board.replies.update', [
                'thread' => $targetThread,
                'reply' => $targetReply,
            ]), [
                'body' => 'modified',
            ]);

        $response->assertRedirect(route('qa-board.show', $targetThread));

        $this->assertDatabaseHas('qa_replies', [
            'id' => $targetReply->id,
            'qa_thread_id' => $targetThread->id,
            'user_id' => $otherStudent->id,
            'body' => 'modified',
        ]);
    }

    public function test_non_author_cannot_update_reply(): void
    {
        $student = User::factory()->student()->create();
        $authorStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $targetReply = QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($authorStudent)
            ->create([
                'body' => 'original',
            ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [
                'thread' => $targetThread,
                'reply' => $targetReply,
            ]), [
                'body' => 'modified',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $targetReply->id,
            'qa_thread_id' => $targetThread->id,
            'user_id' => $authorStudent->id,
            'body' => 'original',
        ]);
    }

    public function test_cannot_update_reply_when_reply_does_not_belong_to_thread(): void
    {
        $student = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $otherThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forStudent($replyAuthor)
            ->create([
                'body' => 'original',
            ]);

        $response = $this->actingAs($replyAuthor)
            ->patch(route('qa-board.replies.update', [
                'thread' => $otherThread,
                'reply' => $reply,
            ]), [
                'body' => 'modified',
            ]);

        $response->assertNotFound();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'qa_thread_id' => $thread->id,
            'user_id' => $replyAuthor->id,
            'body' => 'original',
        ]);
    }

    /**
     * for destroyReply method
     */
    public function test_reply_author_can_delete_own_reply_and_redirect_to_show_page(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $targetReply = QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($otherStudent)
            ->create([
                'body' => 'test',
            ]);

        $response = $this->actingAs($otherStudent)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $targetThread,
                'reply' => $targetReply,
            ]));

        $response->assertRedirect(route('qa-board.show', $targetThread));

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $targetReply->id,
            'qa_thread_id' => $targetThread->id,
            'user_id' => $otherStudent->id,
            'body' => 'test',
        ]);
    }

    public function test_non_author_cannot_delete_reply(): void
    {
        $student = User::factory()->student()->create();
        $authorStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $targetReply = QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($authorStudent)
            ->create([
                'body' => 'original',
            ]);

        $response = $this->actingAs($student)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $targetThread,
                'reply' => $targetReply,
            ]));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $targetReply->id,
            'qa_thread_id' => $targetThread->id,
            'user_id' => $authorStudent->id,
            'body' => 'original',
        ]);
    }

    public function test_cannot_delete_reply_when_reply_does_not_belong_to_thread(): void
    {
        $student = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $otherThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forStudent($replyAuthor)
            ->create([
                'body' => 'original',
            ]);

        $response = $this->actingAs($replyAuthor)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $otherThread,
                'reply' => $reply,
            ]));

        $response->assertNotFound();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'qa_thread_id' => $thread->id,
            'user_id' => $replyAuthor->id,
            'body' => 'original',
        ]);
    }
}
