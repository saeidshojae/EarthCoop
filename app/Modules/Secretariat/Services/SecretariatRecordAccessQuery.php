<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class SecretariatRecordAccessQuery
{
    /**
     * Apply a conservative DB-level authorization prefilter before records enter
     * deterministic or semantic retrieval. RecordPolicy remains the final source
     * of truth and must still be rechecked before a result leaves the service.
     */
    public function apply(Builder $query, User $actor): Builder
    {
        if ($this->isAdministrator($actor)) {
            return $query;
        }

        $userId = (int) $actor->id;

        return $query->where(function (Builder $access) use ($userId) {
            $access
                ->where(function (Builder $ordinary) use ($userId) {
                    $ordinary
                        ->whereIn('secretariat_records.confidentiality', ['public', 'office_members'])
                        ->whereExists(fn (QueryBuilder $office) => $this->accessibleOfficeExists($office, $userId, false));
                })
                ->orWhere(function (Builder $leadership) use ($userId) {
                    $leadership
                        ->where('secretariat_records.confidentiality', 'leadership')
                        ->whereExists(fn (QueryBuilder $office) => $this->accessibleOfficeExists($office, $userId, true));
                })
                ->orWhere(function (Builder $sensitive) use ($userId) {
                    $sensitive
                        ->whereIn('secretariat_records.confidentiality', ['restricted', 'confidential'])
                        ->whereExists(function (QueryBuilder $acl) use ($userId) {
                            $acl->selectRaw('1')
                                ->from('secretariat_acl_entries as sae')
                                ->whereColumn('sae.record_id', 'secretariat_records.id')
                                ->where('sae.permission', 'view')
                                ->whereNull('sae.revoked_at')
                                ->where(function (QueryBuilder $expiry) {
                                    $expiry->whereNull('sae.expires_at')->orWhere('sae.expires_at', '>', now());
                                })
                                ->where(function (QueryBuilder $principal) use ($userId) {
                                    $principal
                                        ->where(function (QueryBuilder $direct) use ($userId) {
                                            $direct->where('sae.principal_type', 'user')
                                                ->where('sae.principal_id', $userId);
                                        })
                                        ->orWhere(function (QueryBuilder $groupAcl) use ($userId) {
                                            $groupAcl->where('sae.principal_type', 'group')
                                                ->whereExists(function (QueryBuilder $membership) use ($userId) {
                                                    $membership->selectRaw('1')
                                                        ->from('group_user as gu_acl')
                                                        ->whereColumn('gu_acl.group_id', 'sae.principal_id')
                                                        ->where('gu_acl.user_id', $userId)
                                                        ->where('gu_acl.status', 1)
                                                        ->where(function (QueryBuilder $validity) {
                                                            $validity->whereNull('gu_acl.expired')
                                                                ->orWhere('gu_acl.expired', 0)
                                                                ->orWhere('gu_acl.expired', '>', now());
                                                        });
                                                });
                                        });
                                });
                        });
                });
        });
    }

    private function accessibleOfficeExists(QueryBuilder $office, int $userId, bool $leadership): void
    {
        $office->selectRaw('1')
            ->from('secretariat_offices as so_access')
            ->whereColumn('so_access.id', 'secretariat_records.office_id')
            ->where('so_access.status', 'active')
            ->where(function (QueryBuilder $scope) use ($userId, $leadership) {
                $scope
                    ->where(function (QueryBuilder $groupOffice) use ($userId, $leadership) {
                        $groupOffice->where('so_access.scope_type', 'group')
                            ->whereExists(function (QueryBuilder $membership) use ($userId, $leadership) {
                                $membership->selectRaw('1')
                                    ->from('group_user as gu_access')
                                    ->whereColumn('gu_access.group_id', 'so_access.scope_id')
                                    ->where('gu_access.user_id', $userId)
                                    ->where('gu_access.status', 1)
                                    ->when($leadership, fn (QueryBuilder $role) => $role->whereIn('gu_access.role', [2, 3]))
                                    ->where(function (QueryBuilder $validity) {
                                        $validity->whereNull('gu_access.expired')
                                            ->orWhere('gu_access.expired', 0)
                                            ->orWhere('gu_access.expired', '>', now());
                                    });
                            });
                    })
                    ->orWhere(function (QueryBuilder $projectOffice) use ($userId) {
                        $projectOffice->where('so_access.scope_type', 'najm_bahar_project')
                            ->whereExists(function (QueryBuilder $project) use ($userId) {
                                $project->selectRaw('1')
                                    ->from('najm_bahar_projects as nbp_access')
                                    ->whereColumn('nbp_access.id', 'so_access.scope_id')
                                    ->where('nbp_access.owner_type', User::class)
                                    ->where('nbp_access.owner_id', $userId);
                            });
                    });
            });
    }

    private function isAdministrator(User $user): bool
    {
        return (bool) $user->is_admin || $user->hasRole('super-admin');
    }
}
