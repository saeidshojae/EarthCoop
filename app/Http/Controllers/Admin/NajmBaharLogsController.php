<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\User;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Http\Request;

class NajmBaharLogsController extends Controller
{
    public function index(Request $request, TemporalService $temporal, TemporalContextResolver $contexts)
    {
        $context = $contexts->forUser($request->user());
        $dateFrom = null;
        $dateTo = null;
        try {
            $dateFrom = $request->filled('date_from') ? $temporal->parseDate((string) $request->input('date_from'), $context)->toCanonical() : null;
            $dateTo = $request->filled('date_to') ? $temporal->parseDate((string) $request->input('date_to'), $context)->toCanonical() : null;
        } catch (\Throwable) {
            // Invalid optional filters are ignored; the submitted value remains visible in the form.
        }

        $logsQuery = AdminActionLog::with('adminUser');

        if ($request->filled('action')) {
            $logsQuery->where('action', 'like', '%' . $request->action . '%');
        }

        if ($request->filled('admin_user_id')) {
            $logsQuery->where('admin_user_id', $request->admin_user_id);
        }

        if ($request->filled('target_type')) {
            $logsQuery->where('target_type', $request->target_type);
        }

        if ($dateFrom) {
            $logsQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $logsQuery->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $logsQuery
            ->orderByDesc('created_at')
            ->paginate(50)
            ->appends($request->query());

        $admins = User::where('is_admin', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $targetTypes = AdminActionLog::query()
            ->select('target_type')
            ->whereNotNull('target_type')
            ->distinct()
            ->orderBy('target_type')
            ->pluck('target_type');

        return view('admin.najm-bahar.logs.index', compact('logs', 'admins', 'targetTypes'));
    }
}
