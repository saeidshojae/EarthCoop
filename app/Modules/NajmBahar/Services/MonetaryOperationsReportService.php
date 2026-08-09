<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\Governance\Models\PublicExecutionReversalRequest;
use Illuminate\Support\Collection;

class MonetaryOperationsReportService
{
    public function problemItems(int $limit = 100): Collection
    {
        $limit = max(1, min($limit, 500));

        $outbox = EconomicAction::query()
            ->whereIn('status', ['failed', 'dead_letter'])
            ->orderByRaw("CASE WHEN status = 'dead_letter' THEN 0 ELSE 1 END")
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (EconomicAction $action) => [
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
            ]);

        $payments = PublicExecutionPaymentInstruction::query()
            ->whereIn('status', ['failed', 'dead_letter'])
            ->orderByRaw("CASE WHEN status = 'dead_letter' THEN 0 ELSE 1 END")
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (PublicExecutionPaymentInstruction $payment) => [
                'kind' => 'contractor_payment',
                'id' => (int) $payment->id,
                'status' => $payment->status,
                'attempts' => (int) $payment->attempts,
                'last_failure_at' => optional($payment->failed_at)->toIso8601String(),
                'error' => $payment->failure_reason,
                'group_id' => (int) optional($payment->plan)->group_id,
                'reference_id' => (int) $payment->payment_instruction_id,
                'operator_action' => $payment->status === 'dead_letter'
                    ? 'recover_dead_letter_then_retry'
                    : 'retry_failed',
            ]);

        $reversals = PublicExecutionReversalRequest::query()
            ->whereIn('status', ['failed', 'dead_letter'])
            ->orderByRaw("CASE WHEN status = 'dead_letter' THEN 0 ELSE 1 END")
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (PublicExecutionReversalRequest $reversal) => [
                'kind' => 'payment_reversal',
                'id' => (int) $reversal->id,
                'status' => $reversal->status,
                'attempts' => (int) $reversal->attempts,
                'last_failure_at' => optional($reversal->failed_at)->toIso8601String(),
                'error' => $reversal->last_error,
                'group_id' => (int) optional(optional($reversal->paymentInstruction)->plan)->group_id,
                'reference_id' => (int) $reversal->payment_instruction_id,
                'operator_action' => $reversal->status === 'dead_letter'
                    ? 'recover_dead_letter_then_retry'
                    : 'retry_failed',
            ]);

        return $outbox
            ->concat($payments)
            ->concat($reversals)
            ->sortBy([
                fn (array $item) => $item['status'] === 'dead_letter' ? 0 : 1,
                fn (array $item) => $item['last_failure_at'] ? -strtotime($item['last_failure_at']) : PHP_INT_MAX,
            ])
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
}
