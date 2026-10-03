<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentGoal\StoreRequest;
use App\Http\Requests\EnrollmentGoal\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\UseCases\EnrollmentGoal\DestroyAction;
use App\UseCases\EnrollmentGoal\MarkAchieveAction;
use App\UseCases\EnrollmentGoal\StoreAction;
use App\UseCases\EnrollmentGoal\UnmarkAchieveAction;
use App\UseCases\EnrollmentGoal\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 個人目標の Controller
 * - create / update / delete / markAchieved / unmarkAchieved は受講生本人のみ可
 *
 * 受講中資格に紐づく個人目標はEnrollmentControllerのshowで表示し、
 * コーチは担当資格に登録した受講生の目標一覧を閲覧でき、管理者は任意受講生の目標一覧を閲覧できる。
 */
class EnrollmentGoalController extends Controller
{
    public function store(Enrollment $enrollment, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($request->user(), $enrollment, $request->validated());

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '個人目標を作成しました。');
    }

    public function edit(EnrollmentGoal $goal): View
    {
        $this->authorize('update', $goal);

        return view('enrollment-goal.edit', [
            'goal' => $goal,
        ]);
    }

    public function update(EnrollmentGoal $goal, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($goal, $request->validated());
        $enrollment = $goal->enrollment;

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '個人目標を更新しました。');
    }

    public function destroy(EnrollmentGoal $goal, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $goal);

        $enrollment = $goal->enrollment;
        $action($goal);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '個人目標を削除しました。');
    }

    public function markAchieve(EnrollmentGoal $goal, MarkAchieveAction $action): RedirectResponse
    {
        $this->authorize('markAchieved', $goal);

        $action($goal);
        $enrollment = $goal->enrollment;

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '個人目標を達成済にしました。');
    }

    public function unmarkAchieve(EnrollmentGoal $goal, UnmarkAchieveAction $action): RedirectResponse
    {
        $this->authorize('unmarkAchieved', $goal);

        $action($goal);
        $enrollment = $goal->enrollment;

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '個人目標を未達成に戻しました。');
    }
}
