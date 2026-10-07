<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Crypt;

class NajmBaharTransferDestinationService
{
    public function preview(User $actor, string $rawAccountNumber): array
    {
        $number = $this->normalize($rawAccountNumber);

        $sub = SubAccount::query()
            ->where('sub_account_code', $number)
            ->where('status', 1)
            ->first();

        if (! $sub instanceof SubAccount) {
            throw (new ModelNotFoundException())->setModel(SubAccount::class);
        }

        $mirror = Account::query()
            ->where('type', 'subaccount')
            ->where('account_number', $number)
            ->where('status', 1)
            ->first();

        if (! $mirror instanceof Account) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        $parent = Account::query()->find((int) $sub->account_id);
        if (! $parent instanceof Account) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        [$ownerType, $ownerKey, $ownerDisplayName] = $this->owner($parent);

        if ($ownerKey === 'user:'.(int) $actor->id) {
            throw new NajmBaharTransferException(
                'transfer_destination_internal',
                'Use an internal transfer flow for your own accounts.',
                409,
            );
        }

        if (! in_array($ownerType, ['user', 'legal_entity'], true)) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        $payload = [
            'v' => 1,
            'account_id' => (int) $mirror->id,
            'account_number' => $number,
            'owner_key' => $ownerKey,
            'exp' => now()->addMinutes(10)->timestamp,
        ];

        return [
            'account_number' => $number,
            'name' => (string) $sub->name,
            'owner_type' => $ownerType,
            'owner_display_name' => $ownerDisplayName,
            'kind' => 'subaccount',
            'status' => (int) $sub->status,
            'destination_token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
    }

    public function verify(User $actor, string $token, string $rawAccountNumber): Account
    {
        $number = $this->normalize($rawAccountNumber);

        try {
            $decoded = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->changed();
        }

        $valid = is_array($decoded ?? null)
            && ($decoded['v'] ?? null) === 1
            && is_int($decoded['account_id'] ?? null)
            && is_string($decoded['account_number'] ?? null)
            && is_string($decoded['owner_key'] ?? null)
            && is_int($decoded['exp'] ?? null);

        if (! $valid
            || $decoded['account_number'] !== $number
            || $decoded['exp'] < now()->timestamp) {
            $this->changed();
        }

        $sub = SubAccount::query()
            ->where('sub_account_code', $number)
            ->where('status', 1)
            ->first();
        $mirror = Account::query()
            ->whereKey((int) $decoded['account_id'])
            ->where('type', 'subaccount')
            ->where('account_number', $number)
            ->where('status', 1)
            ->first();

        if (! $sub instanceof SubAccount || ! $mirror instanceof Account) {
            $this->changed();
        }

        $parent = Account::query()->find((int) $sub->account_id);
        if (! $parent instanceof Account) {
            $this->changed();
        }

        [$ownerType, $ownerKey] = $this->owner($parent);
        if (! in_array($ownerType, ['user', 'legal_entity'], true)
            || $ownerKey !== $decoded['owner_key']
            || $ownerKey === 'user:'.(int) $actor->id) {
            $this->changed();
        }

        return $mirror;
    }

    private function changed(): never
    {
        throw new NajmBaharTransferException(
            'transfer_destination_changed',
            'The transfer destination changed; review it again before sending.',
            409,
        );
    }

    private function normalize(string $raw): string
    {
        $value = preg_replace('/\s+/', '', trim($raw)) ?? '';
        return str_replace('/', '-', $value);
    }

    private function owner(Account $parent): array
    {
        if ($parent->type === 'user' && $parent->user_id) {
            $user = User::query()->find((int) $parent->user_id);
            if (! $user instanceof User) {
                throw (new ModelNotFoundException())->setModel(User::class);
            }

            $name = trim((string) ($user->first_name ?? '').' '.(string) ($user->last_name ?? ''));
            if ($name === '') {
                $name = (string) $parent->name;
            }

            return ['user', 'user:'.(int) $user->id, $name];
        }

        if ($parent->type === 'legal_entity') {
            $meta = (array) ($parent->meta ?? []);
            $groupId = (int) ($meta['group_id'] ?? 0);
            $key = $groupId > 0 ? 'legal_entity:'.$groupId : 'legal_entity:'.(int) $parent->id;
            return ['legal_entity', $key, (string) $parent->name];
        }

        return [(string) $parent->type, 'account:'.(int) $parent->id, (string) $parent->name];
    }
}
