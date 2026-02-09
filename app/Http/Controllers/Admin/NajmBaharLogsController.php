<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\User;
use Illuminate\Http\Request;

class NajmBaharLogsController extends Controller
{
    public function index(Request $request)
    {
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

        if ($request->filled('date_from')) {
            $logsQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $logsQuery->whereDate('created_at', '<=', $request->date_to);
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
