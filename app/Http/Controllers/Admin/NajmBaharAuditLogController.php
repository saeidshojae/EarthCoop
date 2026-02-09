<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NajmBaharAuditLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NajmBaharAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->parseFilters($request);

        $query = NajmBaharAuditLog::query()
            ->when($filters['group_id'], fn($q) => $q->where('group_id', $filters['group_id']))
            ->when($filters['date_from'], function ($q) use ($filters) {
                $q->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
            })
            ->when($filters['date_to'], function ($q) use ($filters) {
                $q->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
            })
            ->when($filters['actor'], function ($q) use ($filters) {
                $q->where(function ($sub) use ($filters) {
                    $sub->where('actor_user_id', $filters['actor'])
                        ->orWhereHas('actor', function ($u) use ($filters) {
                            $u->where('first_name', 'like', '%' . $filters['actor'] . '%')
                                ->orWhere('last_name', 'like', '%' . $filters['actor'] . '%');
                        });
                });
            })
            ->when($filters['action'], fn($q) => $q->where('action', $filters['action']))
            ->when($filters['search'], function ($q) use ($filters) {
                $q->where(function ($sub) use ($filters) {
                    $sub->where('description', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('account_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('sub_account_code', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->orderByDesc('created_at');

        $logs = $query->paginate(50)->withQueryString();

        return view('admin.najm-bahar.audit-logs.index', compact('logs', 'filters'));
    }

    public function export(Request $request)
    {
        $filters = $this->parseFilters($request);

        $logs = NajmBaharAuditLog::query()
            ->when($filters['group_id'], fn($q) => $q->where('group_id', $filters['group_id']))
            ->when($filters['date_from'], function ($q) use ($filters) {
                $q->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
            })
            ->when($filters['date_to'], function ($q) use ($filters) {
                $q->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
            })
            ->when($filters['actor'], function ($q) use ($filters) {
                $q->where(function ($sub) use ($filters) {
                    $sub->where('actor_user_id', $filters['actor'])
                        ->orWhereHas('actor', function ($u) use ($filters) {
                            $u->where('first_name', 'like', '%' . $filters['actor'] . '%')
                                ->orWhere('last_name', 'like', '%' . $filters['actor'] . '%');
                        });
                });
            })
            ->when($filters['action'], fn($q) => $q->where('action', $filters['action']))
            ->when($filters['search'], function ($q) use ($filters) {
                $q->where(function ($sub) use ($filters) {
                    $sub->where('description', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('account_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('sub_account_code', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->orderByDesc('created_at')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="najm-bahar-audit-logs.csv"',
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['date', 'group_id', 'actor', 'role', 'action', 'account', 'sub_account', 'amount', 'direction', 'description']);
            foreach ($logs as $log) {
                $actorName = $log->actor ? trim($log->actor->first_name . ' ' . $log->actor->last_name) : '-';
                fputcsv($handle, [
                    $log->created_at,
                    $log->group_id,
                    $actorName,
                    $log->actor_role,
                    $log->action,
                    $log->account_number,
                    $log->sub_account_code,
                    $log->amount,
                    $log->direction,
                    $log->description,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function parseFilters(Request $request): array
    {
        return [
            'group_id' => $request->input('group_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'actor' => $request->input('actor'),
            'action' => $request->input('action'),
            'search' => $request->input('search'),
        ];
    }
}
