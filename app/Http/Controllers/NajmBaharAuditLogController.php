<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmBaharAuditLog;
use App\Modules\NajmBahar\Services\AccountService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NajmBaharAuditLogController extends Controller
{
    public function indexForGroup(Request $request, Group $group)
    {
        $account = $this->getGroupAccountForMember($group);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به گزارش عملیات مالی گروه برای شما مجاز نیست.');
        }

        $filters = $this->parseFilters($request);

        $query = NajmBaharAuditLog::query()
            ->where('group_id', $group->id)
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

        $logs = $query->paginate(25)->withQueryString();

        $routePrefix = 'groups.najm-bahar.audit-logs';
        $routeParams = ['group' => $group->id];
        $reportOwnerName = 'گروه ' . $group->name;
        $accountNumberDisplay = $account->account_number;

        return view('najm-bahar.audit-logs.index', compact('logs', 'filters', 'routePrefix', 'routeParams', 'reportOwnerName', 'accountNumberDisplay'));
    }

    public function exportForGroup(Request $request, Group $group)
    {
        $account = $this->getGroupAccountForMember($group);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به گزارش عملیات مالی گروه برای شما مجاز نیست.');
        }

        $filters = $this->parseFilters($request);

        $logs = NajmBaharAuditLog::query()
            ->where('group_id', $group->id)
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
            'Content-Disposition' => 'attachment; filename="najm-bahar-group-' . $group->id . '-audit-logs.csv"',
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['date', 'actor', 'role', 'action', 'account', 'sub_account', 'amount', 'direction', 'description']);
            foreach ($logs as $log) {
                $actorName = $log->actor ? trim($log->actor->first_name . ' ' . $log->actor->last_name) : '-';
                fputcsv($handle, [
                    $log->created_at,
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
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'actor' => $request->input('actor'),
            'action' => $request->input('action'),
            'search' => $request->input('search'),
        ];
    }

    private function getGroupAccountForMember(Group $group)
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $isMember = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->exists();

        if (! $isMember) {
            return null;
        }

        return app(AccountService::class)->ensureLegalEntityAccountForGroup($group);
    }
}
