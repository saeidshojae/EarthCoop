<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NajmHodaAutonomyApprovalService
{
    protected string $requestsKey = 'najm_hoda:autonomy:approval:requests';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NotificationService $notificationService
    ) {
    }

    /**
     * @param array<string, mixed> $planItem
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function requestApproval(array $planItem, array $context = []): array
    {
        $now = now();
        $slaMinutes = max(5, (int) config('najm-hoda.runtime.autonomy.human_escalation.sla_minutes', 30));
        $requestId = (string) Str::uuid();

        $request = [
            'id' => $requestId,
            'status' => 'pending',
            'action' => (string) ($planItem['action'] ?? ''),
            'risk' => (string) ($planItem['risk'] ?? 'unknown'),
            'mode' => (string) ($planItem['mode'] ?? 'propose'),
            'requested_at' => $now->toIso8601String(),
            'deadline_at' => $now->addMinutes($slaMinutes)->toIso8601String(),
            'decision_at' => null,
            'decision_by' => null,
            'decision_reason' => null,
            'context' => $context,
            'plan_item' => $planItem,
        ];

        $requests = $this->loadRequests();
        array_unshift($requests, $request);

        $maxHistory = max(20, (int) config('najm-hoda.runtime.autonomy.human_escalation.max_requests_history', 500));
        $requests = array_slice($requests, 0, $maxHistory);
        $this->storeRequests($requests);

        $this->eventBus->emit('najm_hoda.autonomy.approval.requested', [
            'request_id' => $requestId,
            'action' => $request['action'],
            'risk' => $request['risk'],
            'deadline_at' => $request['deadline_at'],
        ]);

        if ((bool) config('najm-hoda.runtime.autonomy.human_escalation.notify_admins', true)) {
            $this->notifyAdmins($request);
        }

        return $request;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pending(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $requests = array_values(array_filter($this->loadRequests(), static function (array $item): bool {
            return (string) ($item['status'] ?? '') === 'pending';
        }));
        $requests = array_slice($requests, 0, $limit);

        return array_map(function (array $request): array {
            $deadline = data_get($request, 'deadline_at');
            $overdue = false;
            if (is_string($deadline) && $deadline !== '') {
                try {
                    $overdue = now()->greaterThan(\Carbon\CarbonImmutable::parse($deadline));
                } catch (\Throwable) {
                    $overdue = false;
                }
            }

            $request['sla_status'] = $overdue ? 'overdue' : 'within_sla';
            return $request;
        }, $requests);
    }

    /**
     * @return array<string, mixed>
     */
    public function decide(string $requestId, string $decision, ?int $reviewerId, ?string $reason = null): array
    {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return ['success' => false, 'reason' => 'invalid_decision'];
        }

        $requests = $this->loadRequests();
        $updated = null;

        foreach ($requests as $index => $request) {
            if ((string) ($request['id'] ?? '') !== $requestId) {
                continue;
            }

            if ((string) ($request['status'] ?? '') !== 'pending') {
                return ['success' => false, 'reason' => 'not_pending'];
            }

            $request['status'] = $decision === 'approve' ? 'approved' : 'rejected';
            $request['decision_at'] = now()->toIso8601String();
            $request['decision_by'] = $reviewerId;
            $request['decision_reason'] = $reason !== null ? trim($reason) : null;

            $requests[$index] = $request;
            $updated = $request;
            break;
        }

        if ($updated === null) {
            return ['success' => false, 'reason' => 'request_not_found'];
        }

        $this->storeRequests($requests);

        $this->eventBus->emit('najm_hoda.autonomy.approval.decided', [
            'request_id' => $requestId,
            'decision' => $decision,
            'decision_by' => $reviewerId,
            'reason' => $updated['decision_reason'],
            'action' => (string) ($updated['action'] ?? ''),
        ]);

        return ['success' => true, 'request' => $updated];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadRequests(): array
    {
        $requests = Cache::get($this->requestsKey, []);
        return is_array($requests) ? $requests : [];
    }

    /**
     * @param array<int, array<string, mixed>> $requests
     */
    protected function storeRequests(array $requests): void
    {
        $ttlMinutes = max(60, (int) config('najm-hoda.runtime.autonomy.human_escalation.retention_minutes', 10080));
        Cache::put($this->requestsKey, $requests, now()->addMinutes($ttlMinutes));
    }

    /**
     * @param array<string, mixed> $request
     */
    protected function notifyAdmins(array $request): void
    {
        $adminIds = User::query()
            ->where('is_admin', 1)
            ->orWhereHas('roles', function ($query): void {
                $query->whereIn('slug', ['super-admin', 'support', 'support_agent']);
            })
            ->pluck('id')
            ->all();

        if (empty($adminIds)) {
            return;
        }

        $title = 'نیاز به تایید انسانی نجم‌هدا';
        $message = 'اکشن ' . (string) ($request['action'] ?? 'unknown')
            . ' با ریسک ' . (string) ($request['risk'] ?? 'unknown')
            . ' در صف تایید قرار گرفت.';

        $this->notificationService->notifyMany(
            $adminIds,
            $title,
            $message,
            url('/admin/najm-hoda/ops'),
            'warning',
            [
                'approval_request_id' => (string) ($request['id'] ?? ''),
                'action' => (string) ($request['action'] ?? ''),
                'risk' => (string) ($request['risk'] ?? ''),
            ]
        );
    }
}
