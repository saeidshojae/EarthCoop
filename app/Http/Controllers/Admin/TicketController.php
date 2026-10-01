<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Support\TicketManagementService;
use App\Temporal\Context\TemporalContext;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        protected TicketManagementService $tickets,
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
    ) {
        $this->middleware('permission:tickets.manage');
    }

    public function index(Request $request)
    {
        $context = $this->temporalContexts->forUser($request->user());
        $query = Ticket::query()->with(['assignee', 'user']);

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
        $this->applyDateFilters($query, $request, $context);

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($s) use ($q) {
                $s->where('tracking_code', 'like', "%{$q}%")
                    ->orWhere('subject', 'like', "%{$q}%")
                    ->orWhere('message', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        $stats = [
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in-progress')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
            'high_priority' => Ticket::where('priority', 'high')->whereIn('status', ['open', 'in-progress'])->count(),
        ];

        $assignees = User::whereHas('roles', function ($q) {
            $q->where('slug', 'like', '%support%');
        })->get();

        $chartData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            $chartData[] = [
                'month' => $this->temporal->date($monthStart, $context, 'short')
                    . ' – '
                    . $this->temporal->date($monthEnd, $context, 'short'),
                'open' => Ticket::where('status', 'open')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count(),
                'closed' => Ticket::where('status', 'closed')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count(),
            ];
        }

        return view('admin.tickets.index', compact('tickets', 'stats', 'assignees', 'chartData'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['assignee', 'user', 'comments.user']);
        $operators = User::whereHas('roles', function ($q) {
            $q->where('slug', 'like', '%support%');
        })->get();

        return view('admin.tickets.show', compact('ticket', 'operators'));
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'assignee_id' => 'nullable|exists:users,id',
        ]);

        $this->tickets->assign($ticket, $data['assignee_id'] ?? null);
        return back()->with('success', 'مسئول تیکت بروزرسانی شد');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        $this->tickets->reply($ticket, (int) $request->user()->id, $data['message'], true);
        return back()->with('success', 'پاسخ ثبت شد');
    }

    public function close(Request $request, Ticket $ticket)
    {
        $this->tickets->close($ticket);
        return back()->with('success', 'تیکت بسته شد');
    }

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
                foreach (Ticket::whereIn('id', $ticketIds)->get() as $ticket) {
                    $this->tickets->close($ticket);
                    $count++;
                }
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
                foreach (Ticket::whereIn('id', $ticketIds)->get() as $ticket) {
                    $this->tickets->assign($ticket, (int) $request->input('assignee_id'));
                    $count++;
                }
                $message = "{$count} تیکت به مسئول اختصاص داده شد.";
                break;

            case 'change_status':
                $request->validate([
                    'status' => 'required|in:open,in-progress,closed',
                ]);
                foreach (Ticket::whereIn('id', $ticketIds)->get() as $ticket) {
                    $this->tickets->changeStatus($ticket, (string) $request->input('status'));
                    $count++;
                }
                $message = "وضعیت {$count} تیکت تغییر کرد.";
                break;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function export(Request $request)
    {
        $context = $this->temporalContexts->forUser($request->user());
        $query = Ticket::query()->with(['assignee', 'user']);

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
        $this->applyDateFilters($query, $request, $context);

        $tickets = $query->orderBy('created_at', 'desc')->get();

        $format = $request->input('format', 'csv');
        $filename = 'tickets_' . now()->format('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($tickets, $context) {
                $file = fopen('php://output', 'w');
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($file, [
                    'شناسه', 'کد پیگیری', 'موضوع', 'وضعیت', 'اولویت', 'کاربر', 'ایمیل', 'مسئول', 'تاریخ ایجاد',
                ]);

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
                        $this->temporal->dateTime($ticket->created_at, $context, 'short'),
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        return back()->with('error', 'فرمت خروجی معتبر نیست.');
    }

    private function applyDateFilters(Builder $query, Request $request, TemporalContext $context): void
    {
        if ($request->filled('from')) {
            try {
                $from = $this->temporal->parseDate((string) $request->input('from'), $context);
                $query->where('created_at', '>=', $this->temporal->startOfDay($from, $context));
            } catch (\Throwable) {
                // Preserve legacy behavior: an invalid optional filter is ignored.
            }
        }

        if ($request->filled('to')) {
            try {
                $to = $this->temporal->parseDate((string) $request->input('to'), $context);
                $query->where('created_at', '<=', $this->temporal->endOfDay($to, $context));
            } catch (\Throwable) {
                // Preserve legacy behavior: an invalid optional filter is ignored.
            }
        }
    }
}
