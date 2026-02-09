<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\InvitationCode;
use App\Models\InvitationCodeLog;
use App\Mail\InvitationMail;
use App\Mail\InvitationRejectedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvitationCodeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('invation')) {
            // Requests tab
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

            $codes = $query->orderBy('created_at', 'desc')->get();

            // Stats
            $now = now();
            $stats = [
                'total' => InvitationCode::count(),
                'used' => InvitationCode::where('used', 1)->count(),
                'expired' => InvitationCode::where('used', 0)->whereNotNull('expire_at')->where('expire_at', '<=', $now)->count(),
                'active' => InvitationCode::where('used', 0)->where(function($q) use ($now){
                    $q->whereNull('expire_at')->orWhere('expire_at', '>', $now);
                })->count(),
                'today' => InvitationCode::whereDate('created_at', $now->toDateString())->count(),
                'week' => InvitationCode::whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count(),
            ];

            // Charts (weekly: last 8 days; monthly: last 12 months)
            $days = collect(range(7,0))->map(function($d){ return now()->subDays($d)->format('Y-m-d'); });
            $createdDaily = [];
            $usedDaily = [];
            foreach ($days as $d) {
                $createdDaily[] = InvitationCode::whereDate('created_at', $d)->count();
                $usedDaily[] = InvitationCode::where('used', 1)
                    ->where(function($q) use ($d){
                        $q->whereDate('used_at', $d)->orWhere(function($qq) use ($d){ $qq->whereNull('used_at')->whereDate('updated_at', $d); });
                    })->count();
            }

            $months = collect(range(11,0))->map(function($m){ return now()->subMonths($m)->format('Y-m'); });
            $createdMonthly = [];
            $usedMonthly = [];
            foreach ($months as $m) {
                $createdMonthly[] = InvitationCode::whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$m])->count();
                $usedMonthly[] = InvitationCode::where('used', 1)
                    ->where(function($q) use ($m){
                        $q->whereRaw("DATE_FORMAT(used_at, '%Y-%m') = ?", [$m])
                          ->orWhere(function($qq) use ($m){ $qq->whereNull('used_at')->whereRaw("DATE_FORMAT(updated_at, '%Y-%m') = ?", [$m]); });
                    })->count();
            }

            // Weekly (last 12 weeks)
            $weeks = collect(range(11,0))->map(function($w){ return now()->startOfWeek()->subWeeks($w); });
            $weekLabels = [];
            $createdWeekly = [];
            $usedWeekly = [];
            foreach ($weeks as $start) {
                $end = (clone $start)->endOfWeek();
                $weekLabels[] = $start->format('Y-m-d') . ' تا ' . $end->format('m-d');
                $createdWeekly[] = InvitationCode::whereBetween('created_at', [$start, $end])->count();
                $usedWeekly[] = InvitationCode::where('used', 1)
                    ->where(function($q) use ($start, $end){
                        $q->whereBetween('used_at', [$start, $end])
                          ->orWhere(function($qq) use ($start, $end){ $qq->whereNull('used_at')->whereBetween('updated_at', [$start, $end]); });
                    })->count();
            }

            $charts = [
                'daily' => [
                    'labels' => $days->map(fn($d)=>substr($d,5))->values(),
                    'created' => $createdDaily,
                    'used' => $usedDaily,
                ],
                'weekly' => [
                    'labels' => $weekLabels,
                    'created' => $createdWeekly,
                    'used' => $usedWeekly,
                ],
                'monthly' => [
                    'labels' => $months->values(),
                    'created' => $createdMonthly,
                    'used' => $usedMonthly,
                ],
            ];
        }

        return view('admin.invitation_codes.index', compact('codes', 'stats', 'charts'));
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
            // Log BEFORE deletion to satisfy FK constraint
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
        // از همان فیلترهای index استفاده می‌کنیم
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
            // UTF-8 BOM
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

        // برای لیست اقدامات یکتا
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

        // log each? avoid heavy; optional
        return back()->withInput()->with('success', "بی‌اعتبارسازی خودکار روی {$affected} کد اعمال شد.");
    }

    public function approveInvitation(Request $request, \App\Models\Invitation $invitation)
    {
        if ($invitation->status !== 0) { // فقط pending
            return back()->with('success', 'این درخواست قبلاً بررسی شده است.');
        }
        // generate unique code
        $length = 6;
        do { $codeStr = strtoupper(substr(bin2hex(random_bytes(8)), 0, $length)); }
        while (InvitationCode::where('code', $codeStr)->exists());

        $code = InvitationCode::create([
            'code' => $codeStr,
            'user_id' => 171,
            'expire_at' => Carbon::now()->addHours(\App\Models\Setting::find(1)->expire_invation_time)
        ]);

        $invitation->status = 1; // issued
        $invitation->reviewed_by = auth()->id();
        $invitation->reviewed_at = now();
        $invitation->save();

        // log
        $this->log($code->id, 'issue', ['invitation_id' => $invitation->id, 'email' => $invitation->email, 'by' => auth()->id()]);

        // Send email with the code
        try {
            \Log::info('Attempting to send invitation email', [
                'email' => $invitation->email,
                'code' => $code->code,
                'mail_driver' => config('mail.default'),
                'mail_host' => config('mail.mailers.smtp.host'),
                'from_address' => config('mail.from.address'),
            ]);
            
            Mail::to($invitation->email)->send(new InvitationMail($code->code, $code->expire_at));
            
            \Log::info('Invitation email sent successfully', [
                'email' => $invitation->email,
                'code' => $code->code,
            ]);
            
            return back()->with('success', 'کد دعوت صادر شد و به ایمیل کاربر ارسال شد.');
        } catch (\Exception $e) {
            \Log::error('Failed to send invitation email', [
                'email' => $invitation->email,
                'code' => $code->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('warning', 'کد دعوت صادر شد اما ارسال ایمیل با خطا مواجه شد. لطفاً لاگ‌ها را بررسی کنید.');
        }
    }

    public function rejectInvitation(Request $request, \App\Models\Invitation $invitation)
    {
        $validated = $request->validate(['admin_note' => 'nullable|string|max:500']);
        $invitation->status = 2; // rejected
        $invitation->admin_note = $validated['admin_note'] ?? null;
        $invitation->reviewed_by = auth()->id();
        $invitation->reviewed_at = now();
        $invitation->save();

        // log
        $this->log(0, 'reject', ['invitation_id' => $invitation->id, 'email' => $invitation->email, 'note' => $invitation->admin_note]);

        // Send rejection email
        try {
            \Log::info('Attempting to send rejection email', [
                'email' => $invitation->email,
                'mail_driver' => config('mail.default'),
            ]);
            
            Mail::to($invitation->email)->send(new InvitationRejectedMail($invitation->admin_note));
            
            \Log::info('Rejection email sent successfully', [
                'email' => $invitation->email,
            ]);
            
            return back()->with('success', 'درخواست رد شد و به ایمیل کاربر اطلاع داده شد.');
        } catch (\Exception $e) {
            \Log::error('Failed to send rejection email', [
                'email' => $invitation->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('warning', 'درخواست رد شد اما ارسال ایمیل با خطا مواجه شد. لطفاً لاگ‌ها را بررسی کنید.');
        }
    }

    public function bulkRequests(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:invitations,id',
            'action' => 'required|in:approve,reject,delete'
        ]);

        $count = 0;
        if ($validated['action'] === 'approve') {
            foreach (\App\Models\Invitation::whereIn('id', $validated['ids'])->get() as $inv) {
                if ($inv->status !== 0) continue;
                $this->approveInvitation($request, $inv);
                $count++;
            }
        } elseif ($validated['action'] === 'reject') {
            foreach (\App\Models\Invitation::whereIn('id', $validated['ids'])->get() as $inv) {
                if ($inv->status === 2) continue;
                $inv->status = 2; // rejected
                $inv->reviewed_by = auth()->id();
                $inv->reviewed_at = now();
                $inv->save();
                $this->log(0, 'reject', ['invitation_id' => $inv->id, 'email' => $inv->email]);
                
                // Send rejection email
                try {
                    \Log::info('Attempting to send bulk rejection email', ['email' => $inv->email]);
                    Mail::to($inv->email)->send(new InvitationRejectedMail($inv->admin_note));
                    \Log::info('Bulk rejection email sent successfully', ['email' => $inv->email]);
                } catch (\Exception $e) {
                    \Log::error('Failed to send bulk rejection email', [
                        'email' => $inv->email,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                $count++;
            }
        } else { // delete
            $count = \App\Models\Invitation::whereIn('id', $validated['ids'])->delete();
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