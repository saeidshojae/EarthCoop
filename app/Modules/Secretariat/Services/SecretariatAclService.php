<?php

namespace App\Modules\Secretariat\Services;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatAclEntry;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SecretariatAclService
{
    private const PRINCIPAL_TYPES = ['user', 'group'];
    private const PERMISSIONS = ['view'];

    public function __construct(private readonly SecretariatAuditService $audit)
    {
    }

    public function grant(
        SecretariatRecord $record,
        string $principalType,
        int $principalId,
        User $grantor,
        string $permission = 'view',
        mixed $expiresAt = null,
        array $metadata = [],
    ): SecretariatAclEntry {
        $this->validateGrant($principalType, $principalId, $permission);
        $record->loadMissing('office');
        Gate::forUser($grantor)->authorize('manageAcl', $record);

        return DB::transaction(function () use ($record, $principalType, $principalId, $grantor, $permission, $expiresAt, $metadata) {
            /** @var SecretariatRecord $locked */
            $locked = SecretariatRecord::query()->with('office')->whereKey($record->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($grantor)->authorize('manageAcl', $locked);

            $active = SecretariatAclEntry::query()
                ->where('record_id', $locked->id)
                ->where('principal_type', $principalType)
                ->where('principal_id', $principalId)
                ->where('permission', $permission)
                ->whereNull('revoked_at')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest('id')
                ->first();

            if ($active !== null) {
                return $active;
            }

            $entry = SecretariatAclEntry::query()->create([
                'record_id' => $locked->id,
                'principal_type' => $principalType,
                'principal_id' => $principalId,
                'permission' => $permission,
                'granted_by' => $grantor->id,
                'granted_at' => now(),
                'expires_at' => $expiresAt,
                'metadata' => $metadata ?: null,
            ]);

            $this->audit->append($locked->office, $locked, $grantor, 'acl_granted', [
                'acl_entry_id' => $entry->id,
                'principal_type' => $principalType,
                'principal_id' => $principalId,
                'permission' => $permission,
                'expires_at' => $entry->expires_at?->toIso8601String(),
            ]);

            return $entry;
        });
    }

    public function revoke(SecretariatAclEntry $entry, User $actor): SecretariatAclEntry
    {
        $entry->loadMissing('record.office');
        Gate::forUser($actor)->authorize('manageAcl', $entry->record);

        return DB::transaction(function () use ($entry, $actor) {
            /** @var SecretariatAclEntry $locked */
            $locked = SecretariatAclEntry::query()->with('record.office')->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageAcl', $locked->record);

            if ($locked->revoked_at !== null) {
                return $locked;
            }

            $locked->performRevocation(function (SecretariatAclEntry $target) use ($actor): void {
                $target->forceFill([
                    'revoked_by' => $actor->id,
                    'revoked_at' => now(),
                ])->save();
            });

            $this->audit->append($locked->record->office, $locked->record, $actor, 'acl_revoked', [
                'acl_entry_id' => $locked->id,
                'principal_type' => $locked->principal_type,
                'principal_id' => $locked->principal_id,
                'permission' => $locked->permission,
            ]);

            return $locked->refresh();
        });
    }

    public function allows(User $user, SecretariatRecord $record, string $permission = 'view'): bool
    {
        if (! in_array($permission, self::PERMISSIONS, true)) {
            return false;
        }

        $base = SecretariatAclEntry::query()
            ->where('record_id', $record->id)
            ->where('permission', $permission)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ((clone $base)->where('principal_type', 'user')->where('principal_id', $user->id)->exists()) {
            return true;
        }

        $groupIds = (clone $base)
            ->where('principal_type', 'group')
            ->pluck('principal_id');

        if ($groupIds->isEmpty()) {
            return false;
        }

        return GroupUser::query()
            ->where('user_id', $user->id)
            ->whereIn('group_id', $groupIds)
            ->where('status', 1)
            ->where(function ($query) {
                $query->whereNull('expired')->orWhere('expired', 0)->orWhere('expired', '>', now());
            })
            ->exists();
    }

    public function auditSensitiveAccess(SecretariatRecord $record, User $actor, array $metadata = []): void
    {
        if ($record->confidentiality !== 'confidential') {
            return;
        }

        $this->audit->append($record->office, $record, $actor, 'access_sensitive', $metadata);
    }

    private function validateGrant(string $principalType, int $principalId, string $permission): void
    {
        if (! in_array($principalType, self::PRINCIPAL_TYPES, true)) {
            throw ValidationException::withMessages(['principal_type' => 'Unsupported Secretariat ACL principal type.']);
        }
        if (! in_array($permission, self::PERMISSIONS, true)) {
            throw ValidationException::withMessages(['permission' => 'Unsupported Secretariat ACL permission.']);
        }
        if ($principalId < 1) {
            throw ValidationException::withMessages(['principal_id' => 'Secretariat ACL principal id must be positive.']);
        }

        $exists = match ($principalType) {
            'user' => User::query()->whereKey($principalId)->exists(),
            'group' => Group::query()->whereKey($principalId)->exists(),
        };

        if (! $exists) {
            throw ValidationException::withMessages(['principal_id' => 'Secretariat ACL principal does not exist.']);
        }
    }
}
