<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NajmHoda\FounderOps\FounderActionAuthorityService;
use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\FounderOps\FounderAttentionService;
use App\Services\NajmHoda\FounderOps\FounderAuthoritySnapshotService;
use App\Services\NajmHoda\FounderOps\FounderOperationsSnapshotService;
use Illuminate\Http\Request;

class FounderOperationsController extends Controller
{
    public function brief(Request $request, FounderAttentionService $service)
    {
        $hours = (int) $request->integer('hours', 24);
        return response()->json(['success' => true, 'data' => $service->brief($hours)]);
    }

    public function snapshot(Request $request, FounderOperationsSnapshotService $service)
    {
        $hours = (int) $request->integer('hours', 24);
        return response()->json(['success' => true, 'data' => $service->snapshot($hours)]);
    }

    public function approvals(FounderApprovalInboxService $service)
    {
        return response()->json(['success' => true, 'data' => $service->snapshot()]);
    }

    public function authority(FounderAuthoritySnapshotService $summary, FounderActionAuthorityService $authority)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary->snapshot(),
                'matrix' => $authority->matrix(),
            ],
        ]);
    }
}
