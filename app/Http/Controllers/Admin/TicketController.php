<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\EmailTicketIntegrationService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    protected EmailTicketIntegrationService $emailService;

    public function __construct(EmailTicketIntegrationService $emailService)
    {
        $this->middleware('permission:tickets.manage');
        $this->emailService = $emailService;
    }

    public function index(Request $request)
    {
        $query = Ticket::query()->with(['assignee', 'user']);

        // فیلتر بر اساس وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // فیلتر بر اساس اولویت
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // فیلتر بر اساس مسئول
        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->input('assignee_id'));
        }

        // فیلتر بر اساس کاربر
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // فیلتر بر اساس تاریخ
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        // جستجو
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function($s) use ($q) {
                $s->where('tracking_code', 'like', "%{$q}%")
                  ->orWhere('subject', 'like', "%{$q}%")
                  ->orWhere('message', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // آمار
        $stats = [
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in-progress')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
            'high_priority' => Ticket::where('priority', 'high')->whereIn('status', ['open', 'in-progress'])->count(),
        ];

        // لیست مسئولان
        $assignees = User::whereHas('roles', function($q){
            $q->where('slug', 'like', '%support%');
        })->get();

        // نمودار روند 12 ماهه
        $chartData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $chartData[] = [
                'month' => \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m'),
                'open' => Ticket::where('status', 'open')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
                'closed' => Ticket::where('status', 'closed')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ];
        }

        return view('admin.tickets.index', compact('tickets', 'stats', 'assignees', 'chartData'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['assignee', 'user', 'comments.user']);
        $operators = User::whereHas('roles', function($q){
            $q->where('slug', 'like', '%support%');
        })->get();

        return view('admin.tickets.show', compact('ticket', 'operators'));
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'assignee_id' => 'nullable|exists:users,id'
        ]);

        $ticket->assignee_id = $data['assignee_id'] ?? null;
        $ticket->save();

        return back()->with('success', 'مسئول تیکت بروزرسانی شد');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'message' => 'required|string'
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message']
        ]);

        // تنظیم first_response_at در صورت اولین پاسخ
        if (!$ticket->first_response_at) {
            $ticket->first_response_at = now();
        }

        // Optionally change status to in-progress when replying
        if ($ticket->status === 'open') {
            $ticket->status = 'in-progress';
        }
        $ticket->save();

        // ارسال پاسخ به ایمیل کاربر
        try {
            $this->emailService->sendTicketReplyToEmail($ticket, $comment);
        } catch (\Exception $e) {
            // لاگ خطا اما ادامه بده
            \Log::error('خطا در ارسال ایمیل پاسخ تیکت: ' . $e->getMessage());
        }

        return back()->with('success', 'پاسخ ثبت شد');
    }

    public function close(Request $request, Ticket $ticket)
    {
        $ticket->status = 'closed';
        $ticket->save();

        return back()->with('success', 'تیکت بسته شد');
    }

    /**
     * Bulk actions on tickets
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:close,delete,assign,change_status',
            'ticket_ids' => 'required|array',
            'ticket_ids.*' => 'exists:tickets,id',
        ]);

        $ticketIds = is_array($request->input('ticket_ids')) 
            ? $request->input('ticket_ids') 
            : explode(',', $request->input('ticket_ids')[0] ?? '');
        $action = $request->input('action');
        $count = 0;

        switch ($action) {
            case 'close':
                Ticket::whereIn('id', $ticketIds)->update(['status' => 'closed']);
                $count = count($ticketIds);
                $message = "{$count} تیکت بسته شد.";
                break;

            case 'delete':
                Ticket::whereIn('id', $ticketIds)->delete();
                $count = count($ticketIds);
                $message = "{$count} تیکت حذف شد.";
                break;

            case 'assign':
                $request->validate([
                    'assignee_id' => 'required|exists:users,id',
                ]);
                Ticket::whereIn('id', $ticketIds)->update(['assignee_id' => $request->input('assignee_id')]);
                $count = count($ticketIds);
                $message = "{$count} تیکت به مسئول اختصاص داده شد.";
                break;

            case 'change_status':
                $request->validate([
                    'status' => 'required|in:open,in-progress,closed',
                ]);
                Ticket::whereIn('id', $ticketIds)->update(['status' => $request->input('status')]);
                $count = count($ticketIds);
                $message = "وضعیت {$count} تیکت تغییر کرد.";
                break;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Export tickets to CSV/Excel
     */
    public function export(Request $request)
    {
        $query = Ticket::query()->with(['assignee', 'user']);

        // اعمال همان فیلترهای index
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->input('assignee_id'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        $format = $request->input('format', 'csv');
        $filename = 'tickets_' . now()->format('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function() use ($tickets) {
                $file = fopen('php://output', 'w');
                
                // BOM برای UTF-8
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // هدرها
                fputcsv($file, [
                    'شناسه',
                    'کد پیگیری',
                    'موضوع',
                    'وضعیت',
                    'اولویت',
                    'کاربر',
                    'ایمیل',
                    'مسئول',
                    'تاریخ ایجاد',
                ]);

                // داده‌ها
                foreach ($tickets as $ticket) {
                    fputcsv($file, [
                        $ticket->id,
                        $ticket->tracking_code,
                        $ticket->subject,
                        $ticket->status,
                        $ticket->priority ?? 'normal',
                        $ticket->user ? $ticket->user->fullName() : ($ticket->name ?? '-'),
                        $ticket->email ?? '-',
                        $ticket->assignee ? $ticket->assignee->fullName() : '-',
                        \Morilog\Jalali\Jalalian::fromCarbon($ticket->created_at)->format('Y/m/d H:i'),
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        return back()->with('error', 'فرمت خروجی معتبر نیست.');
    }
}
