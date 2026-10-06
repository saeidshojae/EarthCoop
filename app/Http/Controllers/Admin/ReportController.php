<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Message;
use App\Models\Poll;
use App\Models\Report;
use App\Models\ReportedMessage;
use App\Models\User;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
    ) {
    }

    public function index(Request $request)
    {
        $stats = $this->getStatistics();
        $chartData = $this->getChartData();
        $filters = [
            'type' => $request->get('type', ''),
            'status' => $request->get('status', ''),
            'priority' => $request->get('priority', ''),
            'date_from' => $request->get('date_from', ''),
            'date_to' => $request->get('date_to', ''),
            'reporter' => $request->get('reporter', ''),
            'search' => $request->get('search', ''),
        ];
        $reports = $this->getCombinedReports($request, 25);

        return view('admin.reports.index', compact('reports', 'stats', 'chartData', 'filters'));
    }

    protected function getCombinedReports(Request $request, $perPage = 25)
    {
        $newReportsQuery = Report::with(['reporter', 'reviewer', 'group']);
        $oldReportsQuery = ReportedMessage::with(['message.user', 'reporter', 'group']);

        if ($request->filled('type')) {
            if ($request->type === 'message') {
                $newReportsQuery->where('type', 'message');
            } else {
                $newReportsQuery->where('type', $request->type);
                $oldReportsQuery->whereRaw('1 = 0');
            }
        }

        if ($request->filled('status')) {
            $newReportsQuery->where('status', $request->status);
            $oldReportsQuery->where('status', $request->status);
        } else {
            $newReportsQuery->whereIn('status', ['pending', 'reviewed']);
            $oldReportsQuery->whereIn('status', ['pending', 'reviewed']);
        }

        if ($request->filled('priority')) {
            $newReportsQuery->where('priority', $request->priority);
        }

        $context = $this->temporalContexts->defaultContext();
        if ($request->filled('date_from')) {
            try {
                $dateFrom = $this->temporal->startOfDay(
                    $this->temporal->parseDate((string) $request->input('date_from'), $context),
                    $context,
                );
                $newReportsQuery->where('created_at', '>=', $dateFrom);
                $oldReportsQuery->where('created_at', '>=', $dateFrom);
            } catch (\Throwable) {
                // Keep the historical behavior: an invalid optional filter is ignored.
            }
        }

        if ($request->filled('date_to')) {
            try {
                $dateTo = $this->temporal->endOfDay(
                    $this->temporal->parseDate((string) $request->input('date_to'), $context),
                    $context,
                );
                $newReportsQuery->where('created_at', '<=', $dateTo);
                $oldReportsQuery->where('created_at', '<=', $dateTo);
            } catch (\Throwable) {
                // Keep the historical behavior: an invalid optional filter is ignored.
            }
        }

        if ($request->filled('reporter')) {
            $newReportsQuery->where('reported_by', $request->reporter);
            $oldReportsQuery->where('reported_by', $request->reporter);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $newReportsQuery->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('reporter', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });

            $oldReportsQuery->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('reporter', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $newReports = $newReportsQuery->orderBy('created_at', 'desc')->get()->map(function ($report) {
            $report->source = 'new';

            return $report;
        });

        $oldReports = $oldReportsQuery->orderBy('created_at', 'desc')->get()->map(function ($report) {
            $report->source = 'old';
            $report->type = 'message';
            $report->reported_item_id = $report->message_id;
            $report->priority = 'medium';
            $report->reviewed_by = null;
            $report->reviewed_at = null;
            $report->report_count = 1;
            $report->metadata = null;

            return $report;
        });

        $allReports = $newReports->concat($oldReports)->sortByDesc('created_at')->values();
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $items = $allReports->slice($offset, $perPage)->values();
        $total = $allReports->count();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    protected function getStatistics()
    {
        $newStats = Report::selectRaw("
            COUNT(*) as `total`,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as `pending`,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as `resolved`,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as `rejected`,
            SUM(CASE WHEN type = 'message' THEN 1 ELSE 0 END) as `messages`,
            SUM(CASE WHEN type = 'post' THEN 1 ELSE 0 END) as `posts`,
            SUM(CASE WHEN type = 'poll' THEN 1 ELSE 0 END) as `polls`,
            SUM(CASE WHEN type = 'user' THEN 1 ELSE 0 END) as `users`,
            SUM(CASE WHEN priority = 'high' OR priority = 'critical' THEN 1 ELSE 0 END) as `high_priority`
        ")->first();

        $oldStats = ReportedMessage::selectRaw("
            COUNT(*) as `total`,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as `pending`,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as `resolved`,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as `rejected`,
            COUNT(*) as `messages`,
            0 as `posts`,
            0 as `polls`,
            0 as `users`,
            0 as `high_priority`
        ")->first();

        return [
            'total' => (int) ($newStats->total ?? 0) + (int) ($oldStats->total ?? 0),
            'pending' => (int) ($newStats->pending ?? 0) + (int) ($oldStats->pending ?? 0),
            'resolved' => (int) ($newStats->resolved ?? 0) + (int) ($oldStats->resolved ?? 0),
            'rejected' => (int) ($newStats->rejected ?? 0) + (int) ($oldStats->rejected ?? 0),
            'messages' => (int) ($newStats->messages ?? 0) + (int) ($oldStats->messages ?? 0),
            'posts' => (int) ($newStats->posts ?? 0),
            'polls' => (int) ($newStats->polls ?? 0),
            'users' => (int) ($newStats->users ?? 0),
            'high_priority' => (int) ($newStats->high_priority ?? 0),
        ];
    }

    protected function getChartData()
    {
        $labels = [];
        $data = [];
        $context = $this->temporalContexts->defaultContext();

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            // The aggregation bucket is a canonical Gregorian month. A localized
            // range label is truthful for every calendar, unlike relabeling that
            // bucket as though it were a whole Jalali month.
            $labels[] = $this->temporal->date($monthStart, $context, 'short')
                . ' – '
                . $this->temporal->date($monthEnd, $context, 'short');

            $count = Report::whereBetween('created_at', [$monthStart, $monthEnd])->count()
                + ReportedMessage::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            $data[] = $count;
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    public function show($id, Request $request)
    {
        $report = Report::with(['reporter', 'reviewer', 'group', 'reportedItem'])->find($id);

        if (! $report) {
            $report = ReportedMessage::with(['message.user', 'reporter', 'group'])->find($id);
            if ($report) {
                $report->source = 'old';
                $report->type = 'message';
            }
        } else {
            $report->source = 'new';
        }

        if (! $report) {
            abort(404);
        }

        $reportedItem = $this->loadReportedItem($report);

        return view('admin.reports.show', compact('report', 'reportedItem'));
    }

    protected function loadReportedItem($report)
    {
        if ($report->source === 'old') {
            return $report->message ?? null;
        }

        return match ($report->type) {
            'message' => Message::with('user', 'group')->find($report->reported_item_id),
            'post' => Blog::with('user', 'group', 'category')->find($report->reported_item_id),
            'poll' => Poll::with('creator', 'group', 'options')->find($report->reported_item_id),
            'user' => User::find($report->reported_item_id),
            default => null,
        };
    }

    public function update(Request $request, $id)
    {
        $report = Report::find($id);

        if (! $report) {
            $report = ReportedMessage::find($id);
            if (! $report) {
                return response()->json(['status' => 'error', 'message' => 'گزارش یافت نشد'], 404);
            }
        }

        $request->validate([
            'status' => 'required|in:pending,reviewed,resolved,rejected,archived',
            'admin_note' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,critical',
        ]);

        $report->status = $request->status;
        if ($request->has('admin_note')) {
            $report->admin_note = $request->admin_note;
        }
        if ($request->has('priority') && isset($report->priority)) {
            $report->priority = $request->priority;
        }

        if (in_array($request->status, ['reviewed', 'resolved', 'rejected']) && auth()->check()) {
            if (isset($report->reviewed_by)) {
                $report->reviewed_by = auth()->id();
                $report->reviewed_at = now();
            }
        }

        $report->save();

        return response()->json([
            'status' => 'success',
            'message' => 'گزارش با موفقیت به‌روزرسانی شد',
        ]);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:approve,reject,archive,delete',
            'report_ids' => 'required|array',
            'report_ids.*' => 'required|integer',
        ]);

        $reportIds = $request->report_ids;
        $action = $request->action;
        $count = 0;

        foreach ($reportIds as $reportId) {
            $report = Report::find($reportId);
            if (! $report) {
                $report = ReportedMessage::find($reportId);
            }

            if (! $report) {
                continue;
            }

            switch ($action) {
                case 'approve':
                    $report->status = 'resolved';
                    if (isset($report->reviewed_by)) {
                        $report->reviewed_by = auth()->id();
                        $report->reviewed_at = now();
                    }
                    $report->save();
                    $count++;
                    break;
                case 'reject':
                    $report->status = 'rejected';
                    if (isset($report->reviewed_by)) {
                        $report->reviewed_by = auth()->id();
                        $report->reviewed_at = now();
                    }
                    $report->save();
                    $count++;
                    break;
                case 'archive':
                    $report->status = 'archived';
                    $report->save();
                    $count++;
                    break;
                case 'delete':
                    $report->delete();
                    $count++;
                    break;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "عملیات با موفقیت روی {$count} گزارش انجام شد",
        ]);
    }

    public function destroy($id)
    {
        $report = Report::find($id);
        if (! $report) {
            $report = ReportedMessage::find($id);
        }

        if (! $report) {
            return response()->json(['status' => 'error', 'message' => 'گزارش یافت نشد'], 404);
        }

        $report->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'گزارش با موفقیت حذف شد',
        ]);
    }
}
