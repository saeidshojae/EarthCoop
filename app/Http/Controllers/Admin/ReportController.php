<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportedMessage;
use App\Models\Message;
use App\Models\Blog;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // آمار کلی
        $stats = $this->getStatistics();
        
        // داده‌ها برای نمودارها
        $chartData = $this->getChartData();
        
        // فیلترها
        $filters = [
            'type' => $request->get('type', ''),
            'status' => $request->get('status', ''),
            'priority' => $request->get('priority', ''),
            'date_from' => $request->get('date_from', ''),
            'date_to' => $request->get('date_to', ''),
            'reporter' => $request->get('reporter', ''),
            'search' => $request->get('search', ''),
        ];

        // گرفتن گزارشات از هر دو جدول و ترکیب آن‌ها
        $reports = $this->getCombinedReports($request, 25);

        return view('admin.reports.index', compact('reports', 'stats', 'chartData', 'filters'));
    }

    protected function getCombinedReports(Request $request, $perPage = 25)
    {
        // گزارشات از جدول جدید
        $newReportsQuery = Report::with(['reporter', 'reviewer', 'group']);
        
        // گزارشات از جدول قدیمی
        $oldReportsQuery = ReportedMessage::with(['message.user', 'reporter', 'group']);

        // فیلتر نوع
        if ($request->filled('type')) {
            if ($request->type === 'message') {
                $newReportsQuery->where('type', 'message');
                // oldReports همیشه message است
            } else {
                $newReportsQuery->where('type', $request->type);
                $oldReportsQuery->whereRaw('1 = 0'); // هیچ نتیجه‌ای نده
            }
        }

        // فیلتر وضعیت
        if ($request->filled('status')) {
            $newReportsQuery->where('status', $request->status);
            $oldReportsQuery->where('status', $request->status);
        } else {
            // پیش‌فرض: فقط pending و reviewed
            $newReportsQuery->whereIn('status', ['pending', 'reviewed']);
            $oldReportsQuery->whereIn('status', ['pending', 'reviewed']);
        }

        // فیلتر اولویت
        if ($request->filled('priority')) {
            $newReportsQuery->where('priority', $request->priority);
        }

        // فیلتر تاریخ
        if ($request->filled('date_from')) {
            try {
                $dateFrom = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $request->date_from)->toCarbon();
                $newReportsQuery->whereDate('created_at', '>=', $dateFrom);
                $oldReportsQuery->whereDate('created_at', '>=', $dateFrom);
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }

        if ($request->filled('date_to')) {
            try {
                $dateTo = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $request->date_to)->toCarbon();
                $newReportsQuery->whereDate('created_at', '<=', $dateTo);
                $oldReportsQuery->whereDate('created_at', '<=', $dateTo);
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }

        // فیلتر گزارش‌دهنده
        if ($request->filled('reporter')) {
            $newReportsQuery->where('reported_by', $request->reporter);
            $oldReportsQuery->where('reported_by', $request->reporter);
        }

        // جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $newReportsQuery->where(function($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('reporter', function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
            
            $oldReportsQuery->where(function($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('reporter', function($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // گرفتن داده‌ها
        $newReports = $newReportsQuery->orderBy('created_at', 'desc')->get()->map(function($report) {
            $report->source = 'new';
            return $report;
        });

        $oldReports = $oldReportsQuery->orderBy('created_at', 'desc')->get()->map(function($report) {
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

        // ترکیب و مرتب‌سازی
        $allReports = $newReports->concat($oldReports)->sortByDesc('created_at')->values();

        // Pagination دستی
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $items = $allReports->slice($offset, $perPage)->values();
        $total = $allReports->count();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    protected function getStatistics()
    {
        // آمار از جدول جدید - استفاده از selectRaw با backtick برای aliasها
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

        // آمار از جدول قدیمی
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
            'total' => (int)($newStats->total ?? 0) + (int)($oldStats->total ?? 0),
            'pending' => (int)($newStats->pending ?? 0) + (int)($oldStats->pending ?? 0),
            'resolved' => (int)($newStats->resolved ?? 0) + (int)($oldStats->resolved ?? 0),
            'rejected' => (int)($newStats->rejected ?? 0) + (int)($oldStats->rejected ?? 0),
            'messages' => (int)($newStats->messages ?? 0) + (int)($oldStats->messages ?? 0),
            'posts' => (int)($newStats->posts ?? 0),
            'polls' => (int)($newStats->polls ?? 0),
            'users' => (int)($newStats->users ?? 0),
            'high_priority' => (int)($newStats->high_priority ?? 0),
        ];
    }

    protected function getChartData()
    {
        // داده‌های 12 ماه گذشته برای نمودار
        $labels = [];
        $data = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $labels[] = \Morilog\Jalali\Jalalian::fromCarbon($monthStart)->format('Y/m');

            $count = Report::whereBetween('created_at', [$monthStart, $monthEnd])->count()
                    + ReportedMessage::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            $data[] = $count;
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    public function show($id, Request $request)
    {
        // بررسی اینکه گزارش از کدام جدول است
        $report = Report::with(['reporter', 'reviewer', 'group', 'reportedItem'])->find($id);
        
        if (!$report) {
            $report = ReportedMessage::with(['message.user', 'reporter', 'group'])->find($id);
            if ($report) {
                $report->source = 'old';
                $report->type = 'message';
            }
        } else {
            $report->source = 'new';
        }

        if (!$report) {
            abort(404);
        }

        // لود آیتم گزارش شده
        $reportedItem = $this->loadReportedItem($report);

        return view('admin.reports.show', compact('report', 'reportedItem'));
    }

    protected function loadReportedItem($report)
    {
        if ($report->source === 'old') {
            return $report->message ?? null;
        }

        switch ($report->type) {
            case 'message':
                return Message::with('user', 'group')->find($report->reported_item_id);
            case 'post':
                return Blog::with('user', 'group', 'category')->find($report->reported_item_id);
            case 'poll':
                return Poll::with('creator', 'group', 'options')->find($report->reported_item_id);
            case 'user':
                return User::find($report->reported_item_id);
            default:
                return null;
        }
    }

    public function update(Request $request, $id)
    {
        $report = Report::find($id);
        
        if (!$report) {
            $report = ReportedMessage::find($id);
            if (!$report) {
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

        // اگر بررسی شده، اطلاعات reviewer را ذخیره کن
        if (in_array($request->status, ['reviewed', 'resolved', 'rejected']) && auth()->check()) {
            if (isset($report->reviewed_by)) {
                $report->reviewed_by = auth()->id();
                $report->reviewed_at = now();
            }
        }

        $report->save();

        return response()->json([
            'status' => 'success',
            'message' => 'گزارش با موفقیت به‌روزرسانی شد'
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
            
            if (!$report) {
                $report = ReportedMessage::find($reportId);
            }

            if ($report) {
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
        }

        return response()->json([
            'status' => 'success',
            'message' => "عملیات با موفقیت روی {$count} گزارش انجام شد"
        ]);
    }

    public function destroy($id)
    {
        $report = Report::find($id);
        
        if (!$report) {
            $report = ReportedMessage::find($id);
        }

        if (!$report) {
            return response()->json(['status' => 'error', 'message' => 'گزارش یافت نشد'], 404);
        }

        $report->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'گزارش با موفقیت حذف شد'
        ]);
    }
}

