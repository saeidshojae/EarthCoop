<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NajmHoda\Integrations\N8n\N8nGateway;
use App\Services\NajmHoda\Integrations\N8n\N8nReadinessService;
use App\Services\NajmHoda\Integrations\N8n\N8nRuntimeControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NajmHodaN8nController extends Controller
{
    public function index(N8nReadinessService $readiness)
    {
        $report = $readiness->report();
        $receipts = DB::table('najm_hoda_n8n_callbacks')
            ->orderByDesc('received_at')
            ->paginate(25);

        $counts = [
            'total' => DB::table('najm_hoda_n8n_callbacks')->count(),
            'completed' => DB::table('najm_hoda_n8n_callbacks')->where('status', 'completed')->count(),
            'progress' => DB::table('najm_hoda_n8n_callbacks')->where('status', 'progress')->count(),
            'failed' => DB::table('najm_hoda_n8n_callbacks')->where('status', 'failed')->count(),
        ];

        return view('admin.najm-hoda.n8n.index', compact('report', 'receipts', 'counts'));
    }

    public function updateControls(Request $request, N8nRuntimeControlService $controls): RedirectResponse
    {
        $validated = $request->validate([
            'outbound_enabled' => 'required|boolean',
            'callback_ingress_enabled' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $controls->update(
            (bool) $validated['outbound_enabled'],
            (bool) $validated['callback_ingress_enabled'],
            $request->user()?->id,
            $validated['reason'] ?? null,
        );

        return back()->with('success', 'کنترل‌های اجرایی n8n به‌روزرسانی شد.');
    }

    public function health(N8nGateway $gateway): RedirectResponse
    {
        try {
            $health = $gateway->health();
            $message = $health['healthy'] ?? false
                ? 'ارتباط n8n سالم است.'
                : 'n8n پاسخ سالم نداد یا در دسترس نیست.';

            return back()->with(($health['healthy'] ?? false) ? 'success' : 'warning', $message);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage());
        }
    }
}
