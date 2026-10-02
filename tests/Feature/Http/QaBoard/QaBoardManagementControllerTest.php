<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaBoardManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * for index method
     */
    public function test_admin_index_lists_all_certifications_threads_with_empty_filters(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();

        $threadForPublishedCertification = QaThread::factory()
            ->forStudent($student)
            ->forCertification($publishedCertification)
            ->create();

        $threadForDraftCertification = QaThread::factory()
            ->forStudent($student)
            ->forCertification($draftCertification)
            ->create();

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk();

        $response->assertViewIs('qa-thread.index');

        $response->assertViewHas('threads', fn ($threads) => $threads->contains('id', $threadForPublishedCertification->id)
            && $threads->contains('id', $threadForDraftCertification->id)
        );
    }

    public function test_index_filters_threads_by_status(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
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

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index', [
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
        $admin = User::factory()->admin()->create();

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

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index', [
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
        $admin = User::factory()->admin()->create();

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

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index', [
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
        $admin = User::factory()->admin()->create();
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

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

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

    /**
     * for show method
     */
    public function test_admin_can_show_thread_with_replies(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
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

        $response = $this->actingAs($admin)->get(route('admin.qa-board.show', $targetThread));

        $response->assertOk();
        $response->assertViewIs('qa-thread.show');

        $response->assertViewHas('thread', fn ($thread) => $thread->id === $targetThread->id
        );

        $response->assertViewHas('replies', fn ($replies) => $replies->contains('id', $targetReply->id)
        );
    }

    /**
     * for destroy method
     */
    public function test_admin_can_delete_thread_with_replies_and_redirect_to_index_page(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $targetThread = QaThread::factory()
            ->forStudent($student)
            ->forCertification($certification)
            ->create();

        $targetReply = QaReply::factory()
            ->forThread($targetThread)
            ->forStudent($student)
            ->create();

        $response = $this->actingAs($admin)->delete(route('admin.qa-board.destroy', $targetThread));

        $response->assertRedirect(route('admin.qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $targetThread->id,
        ]);

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $targetReply->id,
        ]);
    }

    /**
     * for destroyReply method
     */
    public function test_admin_can_delete_reply_and_redirect_to_show_page(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
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

        $response = $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [
                'thread' => $targetThread,
                'reply' => $targetReply,
            ]));

        $response->assertRedirect(route('admin.qa-board.show', $targetThread));

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $targetReply->id,
            'qa_thread_id' => $targetThread->id,
            'user_id' => $otherStudent->id,
            'body' => 'test',
        ]);
    }

    /**
     * other tests
     */
    public function test_student_cannot_access_admin_qa_board(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('admin.qa-board.index'));

        $response->assertForbidden();
    }
}
