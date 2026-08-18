<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NajmHodaRuntimeEvent;
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
            ->select([
                'request_id',
                'correlation_id',
                'workflow',
                'mode',
                'status',
                'remote_run_id',
                'received_at',
            ])
            ->orderByDesc('received_at')
            ->paginate(25);

        $counts = [
            'total' => DB::table('najm_hoda_n8n_callbacks')->count(),
            'completed' => DB::table('najm_hoda_n8n_callbacks')->where('status', 'completed')->count(),
            'progress' => DB::table('najm_hoda_n8n_callbacks')->where('status', 'progress')->count(),
            'failed' => DB::table('najm_hoda_n8n_callbacks')->where('status', 'failed')->count(),
        ];

        $auditEvents = NajmHodaRuntimeEvent::query()
            ->where('event', 'like', 'najm_hoda.integration.n8n.%')
            ->latest('id')
            ->limit(50)
            ->get(['event', 'payload', 'created_at'])
            ->map(static function (NajmHodaRuntimeEvent $entry): array {
                $payload = is_array($entry->payload) ? $entry->payload : [];

                return [
                    'event' => $entry->event,
                    'timestamp' => optional($entry->created_at)->toDateTimeString(),
                    'request_id' => $payload['request_id'] ?? null,
                    'correlation_id' => $payload['correlation_id'] ?? null,
                    'workflow' => $payload['workflow'] ?? null,
                    'status' => $payload['status'] ?? null,
                    'reason' => $payload['reason'] ?? null,
                    'risk' => $payload['risk'] ?? null,
                    'actor_id' => $payload['actor_id'] ?? null,
                ];
            });

        return view('admin.najm-hoda.n8n.index', compact('report', 'receipts', 'counts', 'auditEvents'));
    }

    public function updateControls(Request $request, N8nRuntimeControlService $controls): RedirectResponse
    {
        $allowedNames = array_keys((array) config('najm-hoda-n8n.allowed_workflows', []));
        $validated = $request->validate([
            'outbound_enabled' => 'required|boolean',
            'callback_ingress_enabled' => 'required|boolean',
            'disabled_workflows' => 'nullable|array',
            'disabled_workflows.*' => 'string|max:190',
            'reason' => 'nullable|string|max:500',
        ]);
        $disabled = array_values(array_intersect(
            $allowedNames,
            array_map('strval', (array) ($validated['disabled_workflows'] ?? []))
        ));

        $controls->update(
            (bool) $validated['outbound_enabled'],
            (bool) $validated['callback_ingress_enabled'],
            $request->user()?->id,
            $validated['reason'] ?? null,
            $disabled,
        );

        return back()->with('success', 'کنترل‌های اجرایی n8n به‌روزرسانی شد.');
    }

    public function markSecretRotation(Request $request, N8nRuntimeControlService $controls): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $controls->markSecretRotationVerified($request->user()?->id, $validated['reason'] ?? null);

        return back()->with('success', 'تایید متادیتای چرخش secret ثبت شد؛ مقدار secret ذخیره یا نمایش داده نشد.');
    }

    public function health(N8nGateway $gateway): RedirectResponse
    {
        try {
            $health = $gateway->health();
            $message = $health['healthy'] ?? false
                ? 'ارتباط n8n سالم است.'
                : 'n8n پاسخ سالم نداد یا در دسترس نیست.';

            return back()->with(($health['healthy'] ?? false) ? 'success' : 'warning', $message);
        } catch (RuntimeException) {
            return back()->with('warning', 'Health check در وضعیت فعلی قابل اجرا نیست. تنظیمات سرور را بررسی کنید.');
        }
    }
}
