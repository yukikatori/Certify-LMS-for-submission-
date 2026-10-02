<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaBoard\IndexRequest;
use App\Http\Requests\QaBoard\StoreReplyRequest;
use App\Http\Requests\QaBoard\StoreRequest;
use App\Http\Requests\QaBoard\UpdateReplyRequest;
use App\Http\Requests\QaBoard\UpdateRequest;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaBoard\DestroyAction;
use App\UseCases\QaBoard\DestroyReplyAction;
use App\UseCases\QaBoard\IndexAction;
use App\UseCases\QaBoard\ResolveAction;
use App\UseCases\QaBoard\StoreAction;
use App\UseCases\QaBoard\StoreReplyAction;
use App\UseCases\QaBoard\UnresolveAction;
use App\UseCases\QaBoard\UpdateAction;
use App\UseCases\QaBoard\UpdateReplyAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板（受講生 / コーチ）のコントローラ
 * - student: 公開済資格すべての掲示板に質問を投稿し、コーチや他受講生から回答を得る。また、他受講生の質問・回答を閲覧する。
 * - coach: 担当資格のスレッドに対して返信を行う
 *
 * 管理者モデレーションのためのコントローラは分離する（QaBoardManagementController）
 *
 * アクセス制御(機能群共通)
 * - 受講生は公開済資格すべてのスレッドを閲覧・投稿できる
 * - コーチは担当資格のスレッドのみ閲覧・回答でき、担当外の資格は操作できない
 * - 公開停止中の資格のスレッドは受講生・コーチには見えない(管理者は閲覧できる)
 * - 受講中の受講生・コーチのみアクセスできる
 */
class QaBoardController extends Controller
{
    /**
     * 質問掲示板の一覧表示、フィルタ/ページネーションあり (受講生/コーチ)
     */
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $threads = $action(
            viewer: $request->user(),
            keyword: $validated['keyword'] ?? null,
            status: $validated['status'] ?? null,
            certificationId: $validated['certification_id'] ?? null,
        );

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => [
                'keyword' => $validated['keyword'] ?? '',
                'status' => $validated['status'] ?? '',
                'certification_id' => $validated['certification_id'] ?? '',
            ],
            'certifications' => $action->certifications($request->user()),
            'publishedStatus' => $action->publishedStatus($request->user()),
        ]);
    }

    /**
     * 質問掲示板の質問詳細画面表示 (受講生のみ)
     */
    public function show(QaThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->load(['replies.user', 'certification']);

        return view('qa-thread.show', [
            'thread' => $thread,
            'replies' => $thread->replies ?? collect(),
        ]);
    }

    /**
     * 質問掲示板の質問新規作成画面表示 (受講生のみ)
     */
    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', [
            'certifications' => Certification::published()->orderBy('name')->get(),
        ]);
    }

    /**
     * 質問掲示板の質問新規作成 (受講生のみ)
     */
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $thread = $action($request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', 'スレッドを作成しました。');
    }

    /**
     * 質問掲示板の質問編集画面表示 (投稿者のみ)
     */
    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', [
            'thread' => $thread,
        ]);
    }

    /**
     * 質問掲示板の質問編集 (投稿者のみ)
     */
    public function update(QaThread $thread, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', 'スレッドを更新しました。');
    }

    /**
     * 質問掲示板の質問削除 (投稿者のみ)
     */
    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $action(request()->user(), $thread);

        return redirect()
            ->route('qa-board.index')
            ->with('success', 'スレッドを削除しました。');
    }

    /**
     * 質問掲示板の質問を解決済に変更 (投稿者のみ)
     */
    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', 'スレッドを解決済にしました。');
    }

    /**
     * 質問掲示板の質問を未解決に変更 (投稿者のみ)
     */
    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', 'スレッドを未解決に戻しました。');
    }

    /**
     * 質問掲示板の質問へ回答 (受講生/コーチ)
     */
    public function storeReply(StoreReplyRequest $request, QaThread $thread, StoreReplyAction $action): RedirectResponse
    {
        $action($request->user(), $thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', 'スレッドに回答しました。');
    }

    /**
     * 質問掲示板の質問への回答の編集画面表示 (投稿者のみ)
     */
    public function editReply(QaThread $thread, QaReply $reply): View
    {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    /**
     * 質問掲示板の質問への回答編集 (投稿者のみ)
     */
    public function updateReply(QaThread $thread, QaReply $reply, UpdateReplyRequest $request, UpdateReplyAction $action): RedirectResponse
    {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $action($reply, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    /**
     * 質問掲示板の質問への回答削除 (投稿者のみ)
     */
    public function destroyReply(QaThread $thread, QaReply $reply, DestroyReplyAction $action): RedirectResponse
    {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('delete', $reply);

        $action($reply);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}
