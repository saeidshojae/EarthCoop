<?php

namespace App\Jobs\Communication;

use App\Enums\Communication\CommunicationStatus;
use App\Enums\Communication\DeliveryStatus;
use App\Models\CommunicationRecipient;
use App\Services\Communication\DeliveryFailureClassifier;
use App\Services\Communication\EmailDeliveryAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliverCommunicationRecipient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public function __construct(
        public readonly int $recipientId,
    ) {
    }

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(
        EmailDeliveryAdapter $adapter,
        DeliveryFailureClassifier $classifier,
    ): void {
        $recipient = DB::transaction(function (): ?CommunicationRecipient {
            $recipient = CommunicationRecipient::query()
                ->whereKey($this->recipientId)
                ->lockForUpdate()
                ->first();

            if (! $recipient) {
                return null;
            }

            if (! in_array($recipient->status, [DeliveryStatus::Queued, DeliveryStatus::Retrying], true)) {
                return null;
            }

            $recipient->update(['status' => DeliveryStatus::Sending]);

            return $recipient->fresh();
        });

        if (! $recipient) {
            return;
        }

        $attemptNumber = ((int) $recipient->attempts()->max('attempt_number')) + 1;
        $startedAt = now();

        try {
            $result = $adapter->send($recipient);

            DB::transaction(function () use ($recipient, $attemptNumber, $startedAt, $result): void {
                $recipient->attempts()->create([
                    'attempt_number' => $attemptNumber,
                    'provider' => $result['provider'] ?? 'laravel-mail',
                    'provider_message_id' => $result['provider_message_id'] ?? null,
                    'status' => DeliveryStatus::Sent,
                    'started_at' => $startedAt,
                    'finished_at' => now(),
                ]);

                $recipient->update([
                    'status' => DeliveryStatus::Sent,
                    'sent_at' => now(),
                    'failed_at' => null,
                ]);
            });

            $this->refreshCommunicationStatus($recipient);
        } catch (Throwable $exception) {
            $failureClass = $classifier->classify($exception);

            DB::transaction(function () use ($recipient, $attemptNumber, $startedAt, $exception, $failureClass): void {
                $recipient->attempts()->create([
                    'attempt_number' => $attemptNumber,
                    'status' => DeliveryStatus::Failed,
                    'failure_class' => $failureClass,
                    'failure_code' => class_basename($exception),
                    'failure_message' => $exception->getMessage(),
                    'started_at' => $startedAt,
                    'finished_at' => now(),
                ]);

                if ($failureClass === DeliveryFailureClassifier::PERMANENT) {
                    $recipient->update([
                        'status' => DeliveryStatus::Failed,
                        'failed_at' => now(),
                    ]);
                } else {
                    $recipient->update([
                        'status' => DeliveryStatus::Retrying,
                        'failed_at' => null,
                    ]);
                }
            });

            $this->refreshCommunicationStatus($recipient);

            if ($failureClass === DeliveryFailureClassifier::TRANSIENT) {
                throw $exception;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $recipient = CommunicationRecipient::query()->find($this->recipientId);

        if (! $recipient || $recipient->status === DeliveryStatus::Sent) {
            return;
        }

        $recipient->update([
            'status' => DeliveryStatus::Failed,
            'failed_at' => now(),
        ]);

        $this->refreshCommunicationStatus($recipient);
    }

    private function refreshCommunicationStatus(CommunicationRecipient $recipient): void
    {
        $communication = $recipient->communication()->first();
        if (! $communication) {
            return;
        }

        $statuses = $communication->recipients()->pluck('status')->all();
        $active = ['pending', 'queued', 'sending', 'retrying'];
        $hasActive = count(array_intersect($statuses, $active)) > 0;
        $hasFailure = in_array('failed', $statuses, true);
        $hasSent = in_array('sent', $statuses, true);

        if ($hasActive) {
            $communication->update(['status' => CommunicationStatus::Processing]);
            return;
        }

        if ($hasFailure) {
            $communication->update([
                'status' => $hasSent
                    ? CommunicationStatus::PartiallyFailed
                    : CommunicationStatus::Failed,
            ]);
            return;
        }

        if ($hasSent) {
            $communication->update(['status' => CommunicationStatus::Sent]);
        }
    }
}
