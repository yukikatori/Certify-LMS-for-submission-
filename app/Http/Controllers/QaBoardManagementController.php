<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaBoard\IndexRequest;
use App\Models\QaThread;
use App\UseCases\QaBoard\DestroyAction;
use App\UseCases\QaBoard\DestroyReplyAction;
use App\UseCases\QaBoard\IndexAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板（管理者）のコントローラ
 * - admin : 管理者は専用画面から、公開停止中の資格を含む全資格のスレッドを横断的に閲覧、管理者は任意のスレッド・回答を削除できる
 *
 * 受講生 / コーチによる質問の作成/回答のためのコントローラは分離する（QaBoardController）
 *
 * アクセス制御(機能群共通)
 * - 受講生は公開済資格すべてのスレッドを閲覧・投稿できる
 * - コーチは担当資格のスレッドのみ閲覧・回答でき、担当外の資格は操作できない
 * - 公開停止中の資格のスレッドは受講生・コーチには見えない(管理者は閲覧できる)
 * - 受講中の受講生・コーチのみアクセスできる
 */
class QaBoardManagementController extends Controller
{
    /**
     * 質問掲示板の一覧表示、フィルタ/ページネーションあり (管理者)
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
     * 質問掲示板の質問詳細画面表示 (管理者)
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
     * 質問掲示板の質問削除 (管理者)
     */
    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $action(request()->user(), $thread);

        return redirect()
            ->route('admin.qa-board.index')
            ->with('success', 'スレッドを削除しました。');
    }

    /**
     * 質問掲示板の質問への回答削除 (管理者)
     */
    public function destroyReply($thread, $reply, DestroyReplyAction $action): RedirectResponse
    {
        $thread = QaThread::findOrFail($thread);
        $reply = $thread->replies()->findOrFail($reply);

        $this->authorize('delete', $reply);

        $action($reply);

        return redirect()
            ->route('admin.qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}
