<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\NajmBaharAuditLogger;
use Carbon\Carbon;

class NajmBaharProcessScheduled extends Command
{
    protected $signature = 'najm-bahar:process-scheduled';
    protected $description = 'Process scheduled NajmBahar transactions that are due';

    public function handle()
    {
        $now = Carbon::now();
        $items = ScheduledTransaction::where('status', 'scheduled')
            ->where('execute_at', '<=', $now)
            ->where('attempts', '<', 5)
            ->orderBy('execute_at', 'asc')
            ->limit(100)
            ->get();

        $processed = 0;
        $service = app()->make(TransactionService::class);

        foreach ($items as $item) {
            try {
                DB::transaction(function () use ($item, $service, &$processed) {
                    $payload = $item->payload ?? [];

                    if (($payload['type'] ?? null) === 'subaccount_transfer') {
                        $fromSubAccountId = $payload['from_sub_account_id'] ?? null;
                        $toSubAccountId = $payload['to_sub_account_id'] ?? null;
                        $amount = isset($payload['amount']) ? intval($payload['amount']) : 0;
                        $description = $payload['description'] ?? null;
                        $meta = $payload['metadata'] ?? [];

                        if (! $fromSubAccountId || ! $toSubAccountId || $amount <= 0) {
                            throw new \RuntimeException('Invalid scheduled sub-account payload');
                        }

                        app(SubAccountService::class)->transferBetweenSubAccounts(
                            $fromSubAccountId,
                            $toSubAccountId,
                            $amount,
                            $description
                        );

                        if ($item->transaction_id) {
                            NajmTransaction::where('id', $item->transaction_id)->update([
                                'status' => 'completed',
                            ]);
                        }

                        if (!empty($meta['group_id'])) {
                            $group = \App\Models\Group::find($meta['group_id']);
                            if ($group) {
                                $actor = User::find($meta['actor_user_id'] ?? null);
                                NajmBaharAuditLogger::logGroupAction($group, $actor, [
                                    'action' => 'subaccount.transfer_between_executed',
                                    'account_number' => $meta['from_account_number'] ?? null,
                                    'sub_account_code' => $meta['from_sub_account_code'] ?? null,
                                    'amount' => $amount,
                                    'direction' => 'subaccount_transfer',
                                    'description' => $description ?? 'انتقال زمان بندی شده اجرا شد',
                                    'meta' => [
                                        'from_sub_account_id' => $fromSubAccountId,
                                        'to_sub_account_id' => $toSubAccountId,
                                        'from_sub_account_code' => $meta['from_sub_account_code'] ?? null,
                                        'to_sub_account_code' => $meta['to_sub_account_code'] ?? null,
                                        'scheduled_transaction_id' => $item->id,
                                    ],
                                ]);
                            }
                        } else {
                            NajmBaharAuditLogger::log([
                                'actor_user_id' => $meta['actor_user_id'] ?? null,
                                'action' => 'subaccount.transfer_between_executed',
                                'account_number' => $meta['from_account_number'] ?? null,
                                'sub_account_code' => $meta['from_sub_account_code'] ?? null,
                                'amount' => $amount,
                                'direction' => 'subaccount_transfer',
                                'description' => $description ?? 'انتقال زمان بندی شده اجرا شد',
                                'meta' => [
                                    'from_sub_account_id' => $fromSubAccountId,
                                    'to_sub_account_id' => $toSubAccountId,
                                    'from_sub_account_code' => $meta['from_sub_account_code'] ?? null,
                                    'to_sub_account_code' => $meta['to_sub_account_code'] ?? null,
                                    'scheduled_transaction_id' => $item->id,
                                ],
                            );
                        }

                        $item->status = 'processed';
                        $item->attempts = ($item->attempts ?? 0) + 1;
                        $item->last_error = null;
                        $item->save();

                        $processed++;
                        return;
                    }

                    // Expected payload keys: from_account_number, to_account_number, amount, description, metadata, idempotency_key
                    $from = $payload['from_account_number'] ?? null;
                    $to = $payload['to_account_number'] ?? ($payload['to_account'] ?? null);
                    $amount = isset($payload['amount']) ? intval($payload['amount']) : 0;
                    $description = $payload['description'] ?? null;
                    $meta = $payload['metadata'] ?? [];
                    $idempotency = $payload['idempotency_key'] ?? ($item->id ? "scheduled-{$item->id}" : null);

                    if (!$to || $amount <= 0) {
                        throw new \RuntimeException('Invalid scheduled payload');
                    }

                    // perform transfer (TransactionService will create ledger entries and transactions)
                    $tx = $service->transfer($from, $to, $amount, $description, $meta, $idempotency);

                    // Send notification for scheduled transaction execution
                    // Notify both from and to account users if they exist
                    if ($from) {
                        $fromAccount = Account::where('account_number', $from)->first();
                        if ($fromAccount && $fromAccount->user_id) {
                            $user = User::find($fromAccount->user_id);
                            if ($user) {
                                app(NotificationService::class)->notifyUser(
                                    $user->id,
                                    'تراکنش زمان‌بندی شده اجرا شد',
                                    "تراکنش زمان‌بندی شده شما به مبلغ " . number_format($amount) . " بهار با موفقیت اجرا شد." . ($description ? " توضیحات: " . $description : ""),
                                    route('najm-bahar.dashboard'),
                                    'najm-bahar.scheduled-executed',
                                    [
                                        'transaction_id' => $tx->id,
                                        'amount' => $amount,
                                    ]
                                );
                            }
                        }
                    }
                    
                    if ($to) {
                        $toAccount = Account::where('account_number', $to)->first();
                        if ($toAccount && $toAccount->user_id) {
                            $user = User::find($toAccount->user_id);
                            if ($user) {
                                app(NotificationService::class)->notifyUser(
                                    $user->id,
                                    'تراکنش زمان‌بندی شده اجرا شد',
                                    "تراکنش زمان‌بندی شده شما به مبلغ " . number_format($amount) . " بهار با موفقیت اجرا شد." . ($description ? " توضیحات: " . $description : ""),
                                    route('najm-bahar.dashboard'),
                                    'najm-bahar.scheduled-executed',
                                    [
                                        'transaction_id' => $tx->id,
                                        'amount' => $amount,
                                    ]
                                );
                            }
                        }
                    }

                    // mark scheduled item processed
                    $item->status = 'processed';
                    $item->attempts = ($item->attempts ?? 0) + 1;
                    $item->last_error = null;
                    $item->save();

                    $processed++;
                });
            } catch (\Throwable $e) {
                // record failure and increment attempts
                $item->attempts = ($item->attempts ?? 0) + 1;
                $item->last_error = substr($e->getMessage(), 0, 1000);
                $item->status = $item->attempts >= 5 ? 'failed' : 'scheduled';
                $item->save();

                if ($item->transaction_id) {
                    NajmTransaction::where('id', $item->transaction_id)->update([
                        'status' => $item->status === 'failed' ? 'failed' : 'pending',
                    ]);
                }
                // continue with next item
                continue;
            }
        }

        $this->info('NajmBahar scheduled processing completed. Processed: ' . $processed);
    }
}
