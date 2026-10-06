<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MeetingQuota\StoreRequest;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\UseCases\MeetingQuota\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 受講生向けの追加面談購入フロー。
 */
class MeetingQuotaController extends Controller
{
    public function checkout(): View
    {
        return view('meeting-quota.checkout-select', [
            'plans' => MeetingPack::published()->ordered()->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $checkoutUrl = $action($request->user(), $request->validated('meeting_pack_id'));

        return redirect()->away($checkoutUrl);
    }

    public function success(Request $request): View
    {
        $payment = Payment::query()
            ->with('meetingPack')
            ->where('user_id', $request->user()->id)
            ->whereKey($request->query('payment'))
            ->first();

        return view('meeting-quota.success', [
            'payment' => $payment,
        ]);
    }
}
