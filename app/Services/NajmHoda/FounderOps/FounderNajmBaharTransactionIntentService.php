<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderNajmBaharTransactionIntent;
use App\Modules\NajmBahar\Models\Account;
use Illuminate\Validation\ValidationException;

class FounderNajmBaharTransactionIntentService
{
    /** @return array<string,mixed> */
    public function prepare(
        Account $from,
        Account $to,
        int $amount,
        string $intentKey,
        ?int $requestedBy = null,
        ?string $description = null,
        ?string $transactionType = null,
        array $metadata = []
    ): array {
        $intentKey = trim($intentKey);
        if ($intentKey === '') {
            throw ValidationException::withMessages(['intent_key' => 'A stable transaction intent key is required.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Transaction amount must be a positive integer.']);
        }
        if ((int) $from->id === (int) $to->id) {
            throw ValidationException::withMessages(['to_account' => 'Source and destination accounts must differ.']);
        }
        if ((int) $from->status !== 1 || (int) $to->status !== 1) {
            throw ValidationException::withMessages(['account' => 'Both Najm Bahar accounts must be active.']);
        }
        if (array_key_exists('system_operation', $metadata)) {
            throw ValidationException::withMessages(['metadata' => 'Founder transaction intents cannot request system-operation bypass metadata.']);
        }

        $intentKey = mb_substr($intentKey, 0, 191);
        $idempotencyKey = 'founder-nb-' . hash('sha256', $intentKey);

        $existing = FounderNajmBaharTransactionIntent::query()->where('intent_key', $intentKey)->first();
        if ($existing) {
            $same = (int) $existing->from_account_id === (int) $from->id
                && (int) $existing->to_account_id === (int) $to->id
                && (int) $existing->amount === $amount
                && (string) $existing->balance_type === 'active';
            if (! $same) {
                throw ValidationException::withMessages(['intent_key' => 'Intent key conflicts with a different transaction request.']);
            }
            return $this->summary($existing, 'existing');
        }

        $intent = FounderNajmBaharTransactionIntent::query()->create([
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'requested_by_user_id' => $requestedBy,
            'amount' => $amount,
            'balance_type' => 'active',
            'transaction_type' => $transactionType ? mb_substr(trim($transactionType), 0, 64) : null,
            'intent_key' => $intentKey,
            'idempotency_key' => $idempotencyKey,
            'status' => 'draft',
            'description' => $description ? mb_substr(trim($description), 0, 500) : null,
            'metadata' => $this->safeMetadata($metadata),
        ]);

        return $this->summary($intent, 'created');
    }

    /** @return array<string,mixed> */
    protected function summary(FounderNajmBaharTransactionIntent $intent, string $mode): array
    {
        return [
            'success' => true,
            'status' => 'intent_ready',
            'mode' => $mode,
            'intent_id' => (int) $intent->id,
            'intent_status' => (string) $intent->status,
            'amount' => (int) $intent->amount,
            'balance_type' => (string) $intent->balance_type,
        ];
    }

    /** @return array<string,scalar|null> */
    protected function safeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->filter(fn ($value, $key): bool => $key !== 'system_operation' && (is_scalar($value) || $value === null))
            ->mapWithKeys(fn ($value, $key): array => [mb_substr((string) $key, 0, 64) => is_string($value) ? mb_substr($value, 0, 500) : $value])
            ->all();
    }
}
