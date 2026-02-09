<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportedMessage;
use Illuminate\Http\Request;

class ReportedMessageController extends Controller
{
    public function index()
    {
        // فقط گزارش‌های ارجاع شده به ادمین را نمایش می‌دهد
        $reports = ReportedMessage::with(['message.user', 'reporter', 'group', 'reviewedByManager'])
            ->where('escalated_to_admin', true)
            ->orderBy('escalated_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.reports.index', compact('reports'));
    }

    public function update(Request $request, $id)
    {
        $report = ReportedMessage::find($id);
        $report->status = $request->status;
        $report->save();
        
        return response()->json(['status' => 'success']);
    }
    

    public function destroy($id)
    {
        $report = ReportedMessage::find($id);
        $report->delete();
        
        return response()->json(['status' => 'success']);
    }
} 