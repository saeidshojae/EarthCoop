<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\Governance\Models\PublicExecutionReversalRequest;
use Illuminate\Support\Collection;

class MonetaryOperationsReportService
{
    public const HEALTHY = 'healthy';
    public const WARNING = 'warning';
    public const CRITICAL = 'critical';

    public function problemItems(int $limit = 100): Collection
    {
        $limit = max(1, min($limit, 500));

        $outbox = EconomicAction::query()
            ->whereIn('status', ['failed', 'dead_letter'])
            ->orderByRaw("CASE WHEN status = 'dead_letter' THEN 0 ELSE 1 END")
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (EconomicAction $action) => $this->withSeverity([
                'kind' => 'execution_outbox',
                'id' => (int) $action->id,
                'status' => $action->status,
                'attempts' => (int) $action->attempts,
                'last_failure_at' => optional($action->failed_at)->toIso8601String(),
                'error' => $action->failure_reason,
                'group_id' => (int) $action->group_id,
                'reference_id' => (int) $action->resolution_id,
                'operator_action' => $action->status === 'dead_letter'
                    ? 'recover_dead_letter_then_retry'
                    : 'retry_failed',
            ]));

        $payments = PublicExecutionPaymentInstruction::query()
            ->with('plan:id,group_id')
            ->whereIn('status', ['failed', 'dead_letter'])
            ->orderByRaw("CASE WHEN status = 'dead_letter' THEN 0 ELSE 1 END")
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (PublicExecutionPaymentInstruction $payment) => $this->withSeverity([
                'kind' => 'contractor_payment',
                'id' => (int) $payment->id,
                'status' => $payment->status,
                'attempts' => (int) $payment->attempts,
                'last_failure_at' => optional($payment->failed_at)->toIso8601String(),
                'error' => $payment->failure_reason,
                'group_id' => (int) optional($payment->plan)->group_id,
                'reference_id' => (int) $payment->plan_id,
                'operator_action' => $payment->status === 'dead_letter'
                    ? 'recover_dead_letter_then_retry'
                    : 'retry_failed',
            ]));

        $reversals = PublicExecutionReversalRequest::query()
            ->with('paymentInstruction.plan:id,group_id')
            ->whereIn('status', ['failed', 'dead_letter'])
            ->orderByRaw("CASE WHEN status = 'dead_letter' THEN 0 ELSE 1 END")
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (PublicExecutionReversalRequest $reversal) => $this->withSeverity([
                'kind' => 'payment_reversal',
                'id' => (int) $reversal->id,
                'status' => $reversal->status,
                'attempts' => (int) $reversal->attempts,
                'last_failure_at' => optional($reversal->failed_at)->toIso8601String(),
                'error' => $reversal->last_error,
                'group_id' => (int) optional($reversal->paymentInstruction?->plan)->group_id,
                'reference_id' => (int) $reversal->payment_instruction_id,
                'operator_action' => $reversal->status === 'dead_letter'
                    ? 'recover_dead_letter_then_retry'
                    : 'retry_failed',
            ]));

        return $outbox
            ->concat($payments)
            ->concat($reversals)
            ->sort(function (array $a, array $b) {
                $severityPriority = [self::CRITICAL => 0, self::WARNING => 1];
                $aPriority = $severityPriority[$a['severity']] ?? 2;
                $bPriority = $severityPriority[$b['severity']] ?? 2;
                if ($aPriority !== $bPriority) {
                    return $aPriority <=> $bPriority;
                }

                $aTime = $a['last_failure_at'] ? strtotime($a['last_failure_at']) : 0;
                $bTime = $b['last_failure_at'] ? strtotime($b['last_failure_at']) : 0;
                return $bTime <=> $aTime;
            })
            ->take($limit)
            ->values();
    }

    public function summary(): array
    {
        return [
            'execution_outbox' => [
                'failed' => EconomicAction::where('status', 'failed')->count(),
                'dead_letter' => EconomicAction::where('status', 'dead_letter')->count(),
            ],
            'contractor_payment' => [
                'failed' => PublicExecutionPaymentInstruction::where('status', 'failed')->count(),
                'dead_letter' => PublicExecutionPaymentInstruction::where('status', 'dead_letter')->count(),
            ],
            'payment_reversal' => [
                'failed' => PublicExecutionReversalRequest::where('status', 'failed')->count(),
                'dead_letter' => PublicExecutionReversalRequest::where('status', 'dead_letter')->count(),
            ],
        ];
    }

    public function health(): array
    {
        $summary = $this->summary();
        $failed = (int) collect($summary)->sum(fn (array $counts) => (int) $counts['failed']);
        $deadLetter = (int) collect($summary)->sum(fn (array $counts) => (int) $counts['dead_letter']);
        $severity = $deadLetter > 0 ? self::CRITICAL : ($failed > 0 ? self::WARNING : self::HEALTHY);

        return [
            'severity' => $severity,
            'exit_code' => match ($severity) {
                self::CRITICAL => 2,
                self::WARNING => 1,
                default => 0,
            },
            'failed' => $failed,
            'dead_letter' => $deadLetter,
            'requires_operator_attention' => $deadLetter > 0,
        ];
    }

    private function withSeverity(array $item): array
    {
        $item['severity'] = $item['status'] === 'dead_letter' ? self::CRITICAL : self::WARNING;
        return $item;
    }
}
