<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportReplyDraft;
use App\Services\NajmHoda\FounderOps\FounderActionAuthorityService;
use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\FounderOps\FounderAttentionService;
use App\Services\NajmHoda\FounderOps\FounderAuthoritySnapshotService;
use App\Services\NajmHoda\FounderOps\FounderAutonomyBridgeService;
use App\Services\NajmHoda\FounderOps\FounderOperationsSnapshotService;
use App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService;
use Illuminate\Http\Request;

class FounderOperationsController extends Controller
{
    public function index(
        Request $request,
        FounderAttentionService $attention,
        FounderOperationsSnapshotService $snapshots,
        FounderApprovalInboxService $approvals
    ) {
        $hours = max(1, min((int) $request->integer('hours', 24), 168));

        return view('admin.najm-hoda.founder-ops.index', [
            'hours' => $hours,
            'brief' => $attention->brief($hours),
            'snapshot' => $snapshots->snapshot($hours),
            'approvalInbox' => $approvals->snapshot(),
            'supportDrafts' => SupportReplyDraft::query()
                ->with(['ticket:id,tracking_code,subject,status,priority,category'])
                ->where('status', 'draft')
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function brief(Request $request, FounderAttentionService $service)
    {
        return response()->json(['success' => true, 'data' => $service->brief((int) $request->integer('hours', 24))]);
    }

    public function snapshot(Request $request, FounderOperationsSnapshotService $service)
    {
        return response()->json(['success' => true, 'data' => $service->snapshot((int) $request->integer('hours', 24))]);
    }

    public function autonomyPlan(Request $request, FounderAutonomyBridgeService $service)
    {
        $hours = max(1, min((int) $request->integer('hours', 24), 168));
        $limit = max(1, min((int) $request->integer('limit', 12), 50));
        return response()->json(['success' => true, 'data' => $service->plan($hours, $limit)]);
    }

    public function approvals(FounderApprovalInboxService $service)
    {
        return response()->json(['success' => true, 'data' => $service->snapshot()]);
    }

    public function authority(FounderAuthoritySnapshotService $summary, FounderActionAuthorityService $authority)
    {
        return response()->json(['success' => true, 'data' => ['summary' => $summary->snapshot(), 'matrix' => $authority->matrix()]]);
    }

    public function requestSupportDraftSend(Request $request, SupportReplyDraft $draft, FounderSupportDraftApprovalService $service)
    {
        $result = $service->requestSend($draft, (int) $request->user()->id);
        return back()->with((bool) ($result['success'] ?? ($result['status'] ?? '') === 'awaiting_approval') ? 'success' : 'error',
            ($result['status'] ?? '') === 'awaiting_approval' ? 'درخواست ارسال پاسخ در صف تأیید Founder قرار گرفت.' : 'امکان ایجاد درخواست ارسال وجود ندارد.');
    }

    public function decideSupportDraft(Request $request, string $requestId, FounderSupportDraftApprovalService $service)
    {
        $validated = $request->validate(['decision' => 'required|in:approve,reject', 'reason' => 'nullable|string|max:500']);
        $result = $service->decideAndExecute($requestId, $validated['decision'], (int) $request->user()->id, $validated['reason'] ?? null);
        return back()->with((bool) ($result['success'] ?? false) ? 'success' : 'error',
            (bool) ($result['success'] ?? false) ? 'تصمیم ثبت و مطابق policy اجرا شد.' : 'تصمیم یا اجرای درخواست مجاز نبود.');
    }
}
