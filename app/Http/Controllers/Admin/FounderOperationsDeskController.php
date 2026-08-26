<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FounderAnnouncementDraft;
use App\Models\FounderContentDraft;
use App\Models\FounderEmailDraft;
use App\Models\FounderFinancialRiskFinding;
use App\Models\ModerationCaseSummary;
use App\Models\SupportReplyDraft;
use App\Modules\Secretariat\Models\SecretariatFollowUpProposal;
use App\Services\NajmHoda\FounderOps\FounderAcceptanceStatusService;
use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\FounderOps\FounderAttentionService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveWorkQueueService;
use App\Services\NajmHoda\FounderOps\FounderOperationsSnapshotService;
use App\Services\NajmHoda\FounderOps\FounderReferenceApprovalCandidateService;
use Illuminate\Http\Request;

class FounderOperationsDeskController extends Controller
{
    public function __invoke(
        Request $request,
        FounderAttentionService $attention,
        FounderOperationsSnapshotService $snapshots,
        FounderApprovalInboxService $approvals,
        FounderReferenceApprovalCandidateService $referenceCandidates,
        FounderExecutiveConnectivityService $connectivity,
        FounderExecutiveWorkQueueService $workQueue,
        FounderAcceptanceStatusService $acceptanceStatus,
    ) {
        $hours = max(1, min((int) $request->integer('hours', 24), 168));

        return view('admin.najm-hoda.founder-ops.daily-desk', [
            'hours' => $hours,
            'brief' => $attention->brief($hours),
            'snapshot' => $snapshots->snapshot($hours),
            'executiveConnectivity' => $connectivity->report(),
            'executiveWorkQueue' => $workQueue->snapshot($hours, 50),
            'acceptanceStatus' => $acceptanceStatus->snapshot($hours, 50),
            'approvalInbox' => $approvals->snapshot(),
            'referenceCandidates' => $referenceCandidates->candidates(20),
            'supportDrafts' => SupportReplyDraft::query()->with(['ticket:id,tracking_code,subject,status,priority,category'])->where('status', 'draft')->latest('id')->limit(20)->get(),
            'moderationCases' => ModerationCaseSummary::query()->where('status', 'draft')->latest('id')->limit(20)->get(),
            'secretariatFollowUps' => SecretariatFollowUpProposal::query()->with(['dispatch.record:id,registry_number,status'])->where('status', 'draft')->latest('id')->limit(20)->get(),
            'emailDrafts' => FounderEmailDraft::query()->where('status', 'draft')->latest('id')->limit(20)->get(),
            'contentDrafts' => FounderContentDraft::query()->where('status', 'draft')->latest('id')->limit(20)->get(),
            'contentCategories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'announcementDrafts' => FounderAnnouncementDraft::query()->where('status', 'draft')->latest('id')->limit(20)->get(),
            'financialRiskFindings' => FounderFinancialRiskFinding::query()->where('status', 'open')
                ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->latest('id')->limit(30)->get(),
        ]);
    }
}
