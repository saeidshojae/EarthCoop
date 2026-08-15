<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\Governance\Models\PublicExecutionReversalRequest;
use Illuminate\Support\Carbon;
use Throwable;

class MonetaryRetryCoordinator
{
    /**
     * Backoff after the most recent failed attempt. Index is current attempt count.
     * The fifth failed execution is terminal and is moved to dead_letter by the domain service.
     */
    private const BACKOFF_MINUTES = [
        1 => 5,
        2 => 15,
        3 => 60,
        4 => 360,
    ];

    public function retryDue(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        $remaining = $limit;
        $result = [
            'attempted' => 0,
            'completed' => 0,
            'failed' => 0,
            'dead_letter' => 0,
            'items' => [],
        ];

        foreach ($this->dueOutbox($remaining) as $action) {
            $this->attempt($result, 'execution_outbox', $action->id, function () use ($action) {
                app(GovernanceExecutionOutboxConsumer::class)->consume($action);
            }, fn () => $action->fresh()->status);
            if (--$remaining <= 0) return $result;
        }

        foreach ($this->duePayments($remaining) as $payment) {
            $this->attempt($result, 'contractor_payment', $payment->id, function () use ($payment) {
                app(PublicExecutionPaymentService::class)->execute($payment, true);
            }, fn () => $payment->fresh()->status);
            if (--$remaining <= 0) return $result;
        }

        foreach ($this->dueReversals($remaining) as $reversal) {
            $this->attempt($result, 'payment_reversal', $reversal->id, function () use ($reversal) {
                app(PublicExecutionReversalService::class)->execute($reversal, true);
            }, fn () => $reversal->fresh()->status);
            if (--$remaining <= 0) return $result;
        }

        return $result;
    }

    public function isDue(int $attempts, $failedAt, ?Carbon $now = null): bool
    {
        if ($attempts <= 0 || $attempts >= 5 || ! $failedAt) {
            return false;
        }

        $minutes = self::BACKOFF_MINUTES[$attempts] ?? null;
        if ($minutes === null) {
            return false;
        }

        $now ??= now();
        return Carbon::parse($failedAt)->addMinutes($minutes)->lte($now);
    }

    private function dueOutbox(int $limit)
    {
        return EconomicAction::query()
            ->where('status', 'failed')
            ->whereBetween('attempts', [1, 4])
            ->whereNotNull('failed_at')
            ->orderBy('failed_at')
            ->limit(max(1, $limit * 4))
            ->get()
            ->filter(fn (EconomicAction $item) => $this->isDue((int) $item->attempts, $item->failed_at))
            ->take($limit);
    }

    private function duePayments(int $limit)
    {
        return PublicExecutionPaymentInstruction::query()
            ->where('status', 'failed')
            ->whereBetween('attempts', [1, 4])
            ->whereNotNull('failed_at')
            ->orderBy('failed_at')
            ->limit(max(1, $limit * 4))
            ->get()
            ->filter(fn (PublicExecutionPaymentInstruction $item) => $this->isDue((int) $item->attempts, $item->failed_at))
            ->take($limit);
    }

    private function dueReversals(int $limit)
    {
        return PublicExecutionReversalRequest::query()
            ->where('status', 'failed')
            ->whereBetween('attempts', [1, 4])
            ->whereNotNull('failed_at')
            ->orderBy('failed_at')
            ->limit(max(1, $limit * 4))
            ->get()
            ->filter(fn (PublicExecutionReversalRequest $item) => $this->isDue((int) $item->attempts, $item->failed_at))
            ->take($limit);
    }

    private function attempt(array &$result, string $kind, int $id, callable $operation, callable $status): void
    {
        $result['attempted']++;
        $error = null;

        try {
            $operation();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $finalStatus = (string) $status();
        if (in_array($finalStatus, ['completed', 'executed'], true)) {
            $result['completed']++;
        } elseif ($finalStatus === 'dead_letter') {
            $result['dead_letter']++;
        } else {
            $result['failed']++;
        }

        $result['items'][] = [
            'kind' => $kind,
            'id' => $id,
            'status' => $finalStatus,
            'error' => $error,
        ];
    }
}
