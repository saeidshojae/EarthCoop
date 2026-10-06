<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\InvitationCode;
use App\Models\InvitationCodeLog;
use App\Services\Invitation\InvitationManagementService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvitationCodeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('invation')) {
            $statusMap = ['pending' => 0, 'issued' => 1, 'rejected' => 2];
            $reqQuery = Invitation::query()->with('reviewer');
            if ($request->filled('status') && isset($statusMap[$request->status])) {
                $reqQuery->where('status', $statusMap[$request->status]);
            }
            if ($request->filled('from')) { $reqQuery->whereDate('created_at', '>=', $request->from); }
            if ($request->filled('to')) { $reqQuery->whereDate('created_at', '<=', $request->to); }
            if ($request->filled('q')) { $reqQuery->where('email', 'like', "%{$request->q}%"); }
            $requests = $reqQuery->orderBy('created_at', 'desc')->paginate(25)->withQueryString();
            $codes = collect();
            $stats = null; $charts = null;
            return view('admin.invitation_codes.index', compact('codes', 'stats', 'charts', 'requests'));
        } else {
            $query = InvitationCode::query()->with(['user','usedBy']);

            if ($request->filled('filter')) {
                if ((int)$request->filter === 2) {
                    $query->where('user_id', 171);
                } elseif ((int)$request->filter === 3) {
                    $query->where('user_id', '!=', 171);
                }
            }

            if ($request->filled('status')) {
                if ($request->status === 'used') {
                    $query->where('used', 1);
                } elseif ($request->status === 'unused') {
                    $query->where('used', 0)->where(function($q){
                        $q->whereNull('expire_at')->orWhere('expire_at', '>', now());
                    });
                } elseif ($request->status === 'expired') {
                    $query->where('used', 0)->whereNotNull('expire_at')->where('expire_at', '<=', now());
                }
            }

            if ($request->filled('issuer')) {
                if ($request->issuer === 'system') {
                    $query->where('user_id', 171);
                } elseif ($request->issuer === 'user') {
                    $query->where('user_id', '!=', 171);
                }
            }

            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->from);
            }
            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->to);
            }

            if ($request->filled('q')) {
                $query->where('code', 'like', "%{$request->q}%");
            }

            $codes = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

            [$stats, $charts] = $this->invitationMetrics(now());
        }

        return view('admin.invitation_codes.index', compact('codes', 'stats', 'charts'));
    }


    /** @return array{0:array<string,int>,1:array<string,array<string,mixed>>} */
    private function invitationMetrics(Carbon $now): array
    {
        $todayStart = $now->copy()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();

        $summary = InvitationCode::query()
            ->selectRaw(
                'COUNT(*) as total,
                SUM(CASE WHEN used = 1 THEN 1 ELSE 0 END) as used_count,
                SUM(CASE WHEN used = 0 AND expire_at IS NOT NULL AND expire_at <= ? THEN 1 ELSE 0 END) as expired_count,
                SUM(CASE WHEN used = 0 AND (expire_at IS NULL OR expire_at > ?) THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as today_count,
                SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) as week_count',
                [$now, $now, $todayStart, $tomorrowStart, $weekStart, $weekEnd]
            )
            ->first();

        $stats = [
            'total' => (int) ($summary->total ?? 0),
            'used' => (int) ($summary->used_count ?? 0),
            'expired' => (int) ($summary->expired_count ?? 0),
            'active' => (int) ($summary->active_count ?? 0),
            'today' => (int) ($summary->today_count ?? 0),
            'week' => (int) ($summary->week_count ?? 0),
        ];

        $dayKeys = collect(range(7, 0))
            ->map(fn (int $daysAgo) => $now->copy()->subDays($daysAgo)->format('Y-m-d'));
        $monthKeys = collect(range(11, 0))
            ->map(fn (int $monthsAgo) => $now->copy()->subMonths($monthsAgo)->format('Y-m'));
        $weekStarts = collect(range(11, 0))
            ->map(fn (int $weeksAgo) => $now->copy()->startOfWeek()->subWeeks($weeksAgo));

        $createdDaily = array_fill_keys($dayKeys->all(), 0);
        $usedDaily = array_fill_keys($dayKeys->all(), 0);
        $createdMonthly = array_fill_keys($monthKeys->all(), 0);
        $usedMonthly = array_fill_keys($monthKeys->all(), 0);

        $weekKeys = $weekStarts->map(fn (Carbon $start) => $start->format('Y-m-d'));
        $createdWeekly = array_fill_keys($weekKeys->all(), 0);
        $usedWeekly = array_fill_keys($weekKeys->all(), 0);

        $chartWindowStart = $monthKeys->isEmpty()
            ? $now->copy()->startOfMonth()
            : Carbon::createFromFormat('Y-m', $monthKeys->first())->startOfMonth();

        $rows = InvitationCode::query()
            ->select(['created_at', 'updated_at', 'used', 'used_at'])
            ->where(function ($query) use ($chartWindowStart) {
                $query->where('created_at', '>=', $chartWindowStart)
                    ->orWhere(function ($used) use ($chartWindowStart) {
                        $used->where('used', 1)
                            ->where(function ($timestamp) use ($chartWindowStart) {
                                $timestamp->where('used_at', '>=', $chartWindowStart)
                                    ->orWhere(function ($fallback) use ($chartWindowStart) {
                                        $fallback->whereNull('used_at')
                                            ->where('updated_at', '>=', $chartWindowStart);
                                    });
                            });
                    });
            })
            ->get();

        foreach ($rows as $row) {
            if ($row->created_at) {
                $dayKey = $row->created_at->format('Y-m-d');
                if (array_key_exists($dayKey, $createdDaily)) {
                    $createdDaily[$dayKey]++;
                }

                $monthKey = $row->created_at->format('Y-m');
                if (array_key_exists($monthKey, $createdMonthly)) {
                    $createdMonthly[$monthKey]++;
                }

                $weekKey = $row->created_at->copy()->startOfWeek()->format('Y-m-d');
                if (array_key_exists($weekKey, $createdWeekly)) {
                    $createdWeekly[$weekKey]++;
                }
            }

            if ((bool) $row->used) {
                $usedAt = $row->used_at ?? $row->updated_at;
                if ($usedAt) {
                    $dayKey = $usedAt->format('Y-m-d');
                    if (array_key_exists($dayKey, $usedDaily)) {
                        $usedDaily[$dayKey]++;
                    }

                    $monthKey = $usedAt->format('Y-m');
                    if (array_key_exists($monthKey, $usedMonthly)) {
                        $usedMonthly[$monthKey]++;
                    }

                    $weekKey = $usedAt->copy()->startOfWeek()->format('Y-m-d');
                    if (array_key_exists($weekKey, $usedWeekly)) {
                        $usedWeekly[$weekKey]++;
                    }
                }
            }
        }

        $charts = [
            'daily' => [
                'labels' => $dayKeys->map(fn (string $day) => substr($day, 5))->values(),
                'created' => array_values($createdDaily),
                'used' => array_values($usedDaily),
            ],
            'weekly' => [
                'labels' => $weekStarts
                    ->map(fn (Carbon $start) => $start->format('Y-m-d') . ' تا ' . $start->copy()->endOfWeek()->format('m-d'))
                    ->values(),
                'created' => array_values($createdWeekly),
                'used' => array_values($usedWeekly),
            ],
            'monthly' => [
                'labels' => $monthKeys->values(),
                'created' => array_values($createdMonthly),
                'used' => array_values($usedMonthly),
            ],
        ];

        return [$stats, $charts];
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:invitation_codes,code'
        ]);

        $code = InvitationCode::create([
            'code' => $request->code,
            'user_id' => 171,
            'expire_at' => Carbon::now()->addHours(\App\Models\Setting::find(1)->expire_invation_time)
        ]);

        $this->log($code->id, 'create', ['by' => auth()->id()]);

        return redirect()->route('admin.invitation_codes.index')->with('success', 'کد دعوت با موفقیت ایجاد شد.');
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:invitation_codes,id',
            'action' => 'required|in:delete,invalidate'
        ]);

        $affected = 0;
        if ($validated['action'] === 'delete') {
            foreach ($validated['ids'] as $id) { $this->log($id, 'delete', ['by' => auth()->id()]); }
            $affected = InvitationCode::whereIn('id', $validated['ids'])->delete();
        } elseif ($validated['action'] === 'invalidate') {
            $affected = InvitationCode::whereIn('id', $validated['ids'])
                ->update(['expire_at' => Carbon::now()->subMinute()]);
            foreach ($validated['ids'] as $id) { $this->log($id, 'invalidate', ['by' => auth()->id()]); }
        }

        return back()->with('success', "عملیات با موفقیت روی {$affected} رکورد اعمال شد.");
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:500',
            'length' => 'required|integer|min:4|max:32',
            'prefix' => 'nullable|string|max:20',
            'suffix' => 'nullable|string|max:20',
            'separator' => 'nullable|string|max:1',
            'issuer' => 'nullable|in:system,user',
        ]);

        $issuerUserId = $validated['issuer'] === 'user' ? (auth()->id() ?? 171) : 171;
        $generated = [];
        $tries = 0;
        while (count($generated) < $validated['count'] && $tries < ($validated['count'] * 10)) {
            $tries++;
            $random = strtoupper(substr(bin2hex(random_bytes(ceil($validated['length'] / 2))), 0, $validated['length']));
            $codeStr = ($validated['prefix'] ?? '')
                . (($validated['separator'] ?? '') && ($validated['prefix'] ?? '') ? $validated['separator'] : '')
                . $random
                . (($validated['separator'] ?? '') && ($validated['suffix'] ?? '') ? $validated['separator'] : '')
                . ($validated['suffix'] ?? '');

            if (!InvitationCode::where('code', $codeStr)->exists()) {
                $code = InvitationCode::create([
                    'code' => $codeStr,
                    'user_id' => $issuerUserId,
                    'expire_at' => Carbon::now()->addHours(\App\Models\Setting::find(1)->expire_invation_time)
                ]);
                $this->log($code->id, 'generate', ['by' => auth()->id()]);
                $generated[] = $codeStr;
            }
        }

        return back()->with('success', count($generated) . ' کد جدید ایجاد شد.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $request->merge(['invation' => null]);
        $query = InvitationCode::query();

        if ($request->filled('status')) {
            if ($request->status === 'used') $query->where('used', 1);
            if ($request->status === 'unused') $query->where('used', 0)->where(function($q){
                $q->whereNull('expire_at')->orWhere('expire_at', '>', now());
            });
            if ($request->status === 'expired') $query->where('used', 0)->whereNotNull('expire_at')->where('expire_at', '<=', now());
        }
        if ($request->filled('issuer')) {
            if ($request->issuer === 'system') $query->where('user_id', 171);
            if ($request->issuer === 'user') $query->where('user_id', '!=', 171);
        }
        if ($request->filled('from')) { $query->whereDate('created_at', '>=', $request->from); }
        if ($request->filled('to')) { $query->whereDate('created_at', '<=', $request->to); }
        if ($request->filled('q')) { $query->where('code', 'like', "%{$request->q}%"); }

        $filename = 'invitation-codes-' . date('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'کد', 'وضعیت', 'صادرکننده', 'استفاده‌کننده', 'تاریخ ایجاد', 'تاریخ انقضا']);
            $query->orderBy('created_at', 'desc')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $code) {
                    $status = $code->used ? 'استفاده شده' : ((optional($code->expire_at) && $code->expire_at <= now()) ? 'منقضی' : 'استفاده نشده');
                    fputcsv($out, [
                        $code->id,
                        $code->code,
                        $status,
                        optional($code->user)->fullName() ?? '-',
                        optional($code->usedBy)->fullName() ?? '-',
                        optional($code->created_at)->format('Y-m-d H:i'),
                        optional($code->expire_at)->format('Y-m-d H:i'),
                    ]);
                }
            });
            fclose($out);
        }, 200, $headers);
    }

    public function logs(Request $request)
    {
        $query = InvitationCodeLog::with(['code.user', 'actor']);

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->whereHas('code', function ($sub) use ($q) {
                $sub->where('code', 'like', "%{$q}%");
            });
        }
        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->actor_id);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();
        $actions = InvitationCodeLog::select('action')->distinct()->pluck('action');

        return view('admin.invitation_codes.logs', compact('logs', 'actions'));
    }

    public function exportLogs(Request $request): StreamedResponse
    {
        $query = InvitationCodeLog::with(['code.user', 'actor']);
        if ($request->filled('action')) $query->where('action', $request->action);
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->to);
        if ($request->filled('q')) {
            $q = $request->q;
            $query->whereHas('code', function ($sub) use ($q) {
                $sub->where('code', 'like', "%{$q}%");
            });
        }
        if ($request->filled('actor_id')) $query->where('actor_id', $request->actor_id);

        $filename = 'invitation-code-logs-' . date('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'کد', 'اقدام', 'صادرکننده', 'عامل', 'تاریخ', 'متادیتا']);
            $query->orderBy('created_at', 'desc')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $log) {
                    fputcsv($out, [
                        $log->id,
                        optional($log->code)->code,
                        $log->action,
                        optional(optional($log->code)->user)->fullName() ?? '-',
                        optional($log->actor)->fullName() ?? '-',
                        optional($log->created_at)->format('Y-m-d H:i'),
                        json_encode($log->meta, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
            fclose($out);
        }, 200, $headers);
    }

    public function autoInvalidate(Request $request)
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:3650'
        ]);
        $threshold = now()->subDays($validated['days']);
        $affected = InvitationCode::where('used', 0)
            ->where(function($q){ $q->whereNull('expire_at')->orWhere('expire_at', '>', now()); })
            ->where('created_at', '<=', $threshold)
            ->update(['expire_at' => now()->subMinute()]);

        return back()->withInput()->with('success', "بی‌اعتبارسازی خودکار روی {$affected} کد اعمال شد.");
    }

    public function approveInvitation(
        Request $request,
        Invitation $invitation,
        InvitationManagementService $invitations,
    ) {
        $result = $invitations->issue($invitation, (int) auth()->id());

        if (($result['status'] ?? null) === 'already_reviewed') {
            return back()->with('success', 'این درخواست قبلاً بررسی شده است.');
        }

        if ((bool) ($result['mail_queued'] ?? $result['mail_sent'] ?? false)) {
            return back()->with('success', 'کد دعوت صادر شد و ارسال ایمیل در صف قرار گرفت.');
        }

        return back()->with('warning', 'کد دعوت صادر شد اما ثبت ایمیل در صف ارتباطات با خطا مواجه شد. لطفاً لاگ‌ها را بررسی کنید.');
    }

    public function rejectInvitation(
        Request $request,
        Invitation $invitation,
        InvitationManagementService $invitations,
    ) {
        $validated = $request->validate(['admin_note' => 'nullable|string|max:500']);
        $result = $invitations->reject(
            $invitation,
            (int) auth()->id(),
            $validated['admin_note'] ?? null,
        );

        if (($result['status'] ?? null) === 'already_reviewed') {
            return back()->with('success', 'این درخواست قبلاً بررسی شده است.');
        }

        if ((bool) ($result['mail_queued'] ?? $result['mail_sent'] ?? false)) {
            return back()->with('success', 'درخواست رد شد و ایمیل اطلاع‌رسانی در صف قرار گرفت.');
        }

        return back()->with('warning', 'درخواست رد شد اما ثبت ایمیل در صف ارتباطات با خطا مواجه شد. لطفاً لاگ‌ها را بررسی کنید.');
    }

    public function bulkRequests(Request $request, InvitationManagementService $invitations)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:invitations,id',
            'action' => 'required|in:approve,reject,delete'
        ]);

        $count = 0;
        if ($validated['action'] === 'approve') {
            foreach (Invitation::whereIn('id', $validated['ids'])->get() as $inv) {
                if ((int) $inv->status !== 0) continue;
                $invitations->issue($inv, (int) auth()->id());
                $count++;
            }
        } elseif ($validated['action'] === 'reject') {
            foreach (Invitation::whereIn('id', $validated['ids'])->get() as $inv) {
                if ((int) $inv->status !== 0) continue;
                $invitations->reject($inv, (int) auth()->id(), $inv->admin_note);
                $count++;
            }
        } else {
            $count = Invitation::whereIn('id', $validated['ids'])->delete();
        }

        return back()->with('success', "عملیات روی {$count} درخواست انجام شد.");
    }

    private function log(int $invitationCodeId, string $action, array $meta = []): void
    {
        if (class_exists(InvitationCodeLog::class)) {
            InvitationCodeLog::create([
                'invitation_code_id' => $invitationCodeId > 0 ? $invitationCodeId : null,
                'action' => $action,
                'actor_id' => auth()->id(),
                'meta' => $meta,
            ]);
        }
    }
}
