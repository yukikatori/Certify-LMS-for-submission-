<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentNote\StoreRequest;
use App\Http\Requests\EnrollmentNote\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\UseCases\EnrollmentNote\DestroyAction;
use App\UseCases\EnrollmentNote\StoreAction;
use App\UseCases\EnrollmentNote\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 受講生メモの Controller。
 * コーチ(担当資格)と管理者がメモの追加 / 編集 / 削除を行う。
 */
class EnrollmentNoteController extends Controller
{
    public function store(StoreRequest $request, Enrollment $enrollment, StoreAction $action): RedirectResponse
    {
        $action($request->user(), $enrollment, $request->validated());

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', 'コーチメモを作成しました。');
    }

    public function edit(EnrollmentNote $note): View
    {
        $this->authorize('update', $note);

        return view('enrollment-note.edit', [
            'note' => $note,
        ]);
    }

    public function update(EnrollmentNote $note, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($note, $request->user(), $request->validated());
        $enrollment = $note->enrollment;

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', 'コーチメモを更新しました。');
    }

    public function destroy(EnrollmentNote $note, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $note);

        $enrollment = $note->enrollment;
        $action($note);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', 'コーチメモを削除しました。');
    }
}
