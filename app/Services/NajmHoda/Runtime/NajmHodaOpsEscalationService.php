<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\Ticket;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TicketTriageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NajmHodaOpsEscalationService
{
    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected TicketTriageService $ticketTriage,
        protected NotificationService $notificationService
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $incidents
     * @return array<int, array<string, mixed>>
     */
    public function escalate(array $snapshot, array $incidents, bool $dryRun = false): array
    {
        if (!(bool) config('najm-hoda.runtime.ops.escalation.enabled', true)) {
            return [];
        }

        $maxPerRun = max(1, (int) config('najm-hoda.runtime.ops.escalation.max_incidents_per_run', 3));
        $cooldownSeconds = max(60, (int) config('najm-hoda.runtime.ops.escalation.cooldown_seconds', 900));

        $results = [];
        $selected = array_slice($incidents, 0, $maxPerRun);

        foreach ($selected as $incident) {
            $severity = (string) ($incident['severity'] ?? 'warning');
            if (!in_array($severity, ['warning', 'critical'], true)) {
                continue;
            }

            $code = (string) ($incident['code'] ?? 'OPS_UNKNOWN');
            $cooldownKey = "najm_hoda:ops:escalation:{$code}";
            $hasCooldown = Cache::has($cooldownKey);

            if ($hasCooldown) {
                $skipPayload = [
                    'incident_code' => $code,
                    'reason' => 'cooldown_active',
                ];
                $this->eventBus->emit('najm_hoda.ops.escalation.skipped', $skipPayload);
                $results[] = array_merge($skipPayload, ['action' => 'skipped']);
                continue;
            }

            if ($dryRun) {
                $dryPayload = [
                    'incident_code' => $code,
                    'severity' => $severity,
                    'action' => 'dry_run',
                ];
                $this->eventBus->emit('najm_hoda.ops.escalation.dry_run', $dryPayload);
                $results[] = $dryPayload;
                continue;
            }

            $subject = $this->buildSubject($incident);
            $message = $this->buildMessage($snapshot, $incident);

            $triage = $this->ticketTriage->triage($subject, $message);
            $priority = $severity === 'critical'
                ? 'high'
                : (string) ($triage['priority'] ?? 'normal');

            $ticket = Ticket::query()->create([
                'user_id' => null,
                'name' => 'Najm Hoda Ops',
                'email' => env('SUPPORT_EMAIL'),
                'subject' => $subject,
                'message' => $message,
                'status' => 'open',
                'priority' => $priority,
                'assignee_id' => $triage['assignee_id'] ?? null,
                'tracking_code' => strtoupper(Str::random(8)),
            ]);

            Cache::put($cooldownKey, $ticket->id, now()->addSeconds($cooldownSeconds));

            $createdPayload = [
                'incident_code' => $code,
                'severity' => $severity,
                'ticket_id' => $ticket->id,
                'priority' => $priority,
            ];
            $this->eventBus->emit('najm_hoda.ops.escalation.created', $createdPayload);

            if ((bool) config('najm-hoda.runtime.ops.escalation.notify_admins', true)) {
                $this->notifyAdmins($incident, $ticket);
            }

            $results[] = array_merge($createdPayload, ['action' => 'created']);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $incident
     */
    protected function buildSubject(array $incident): string
    {
        $code = (string) ($incident['code'] ?? 'OPS_UNKNOWN');
        $title = (string) ($incident['title'] ?? 'Najm Hoda ops incident');
        return "[OpsIncident:{$code}] {$title}";
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $incident
     */
    protected function buildMessage(array $snapshot, array $incident): string
    {
        $status = (string) ($snapshot['status'] ?? 'unknown');
        $errorRate = (float) data_get($snapshot, 'metrics.error_rate_percent', 0);
        $unresolved = (int) data_get($snapshot, 'metrics.unresolved_requests', 0);
        $details = (array) ($incident['details'] ?? []);

        return implode("\n", [
            'Najm Hoda operational incident detected.',
            'Status: ' . $status,
            'Severity: ' . (string) ($incident['severity'] ?? 'warning'),
            'Code: ' . (string) ($incident['code'] ?? 'OPS_UNKNOWN'),
            'Title: ' . (string) ($incident['title'] ?? ''),
            'Error rate: ' . $errorRate . '%',
            'Unresolved requests: ' . $unresolved,
            'Generated at: ' . now()->toIso8601String(),
            'Details: ' . json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param array<string, mixed> $incident
     */
    protected function notifyAdmins(array $incident, Ticket $ticket): void
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

        $severity = (string) ($incident['severity'] ?? 'warning');
        $title = $severity === 'critical'
            ? 'هشدار بحرانی نجـم‌هدا'
            : 'هشدار عملیاتی نجـم‌هدا';

        $message = 'Incident با کد ' . (string) ($incident['code'] ?? 'OPS_UNKNOWN')
            . ' ثبت شد. Ticket #' . $ticket->id . ' ایجاد شده است.';

        $this->notificationService->notifyMany(
            $adminIds,
            $title,
            $message,
            url('/admin/tickets/' . $ticket->id),
            $severity === 'critical' ? 'error' : 'warning',
            [
                'ticket_id' => $ticket->id,
                'incident_code' => (string) ($incident['code'] ?? 'OPS_UNKNOWN'),
            ]
        );
    }
}

