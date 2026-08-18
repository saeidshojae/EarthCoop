<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecretariatCaseService
{
    private const STATUSES = ['open', 'on_hold', 'closed', 'archived'];
    private const CONFIDENTIALITIES = ['public', 'office_members', 'leadership', 'restricted', 'confidential'];
    private const TRANSITIONS = [
        'open' => ['on_hold', 'closed'],
        'on_hold' => ['open', 'closed'],
        'closed' => ['open', 'archived'],
        'archived' => [],
    ];

    public function __construct(
        private readonly SecretariatAuditService $audit,
        private readonly RegistryNumberService $numbers,
    ) {
    }

    /** @param array<string,mixed> $attributes */
    public function create(SecretariatOffice $office, User $actor, array $attributes): SecretariatCase
    {
        $title = trim((string) ($attributes['title'] ?? ''));
        $confidentiality = (string) ($attributes['confidentiality'] ?? $office->default_confidentiality ?? 'office_members');
        $requestedNumber = isset($attributes['case_number']) ? trim((string) $attributes['case_number']) : null;

        if ($title === '' || mb_strlen($title) > 500) {
            throw ValidationException::withMessages(['title' => 'A Secretariat case requires a title up to 500 characters.']);
        }
        if (! in_array($confidentiality, self::CONFIDENTIALITIES, true)) {
            throw ValidationException::withMessages(['confidentiality' => 'Unsupported case confidentiality.']);
        }
        if ($requestedNumber !== null && ($requestedNumber === '' || mb_strlen($requestedNumber) > 160)) {
            throw ValidationException::withMessages(['case_number' => 'Case number must be a non-empty value up to 160 characters.']);
        }

        return DB::transaction(function () use ($office, $actor, $attributes, $title, $confidentiality, $requestedNumber) {
            /** @var SecretariatOffice $lockedOffice */
            $lockedOffice = SecretariatOffice::query()->whereKey($office->id)->lockForUpdate()->firstOrFail();

            if ($requestedNumber !== null) {
                if (SecretariatCase::query()->where('office_id', $lockedOffice->id)->where('case_number', $requestedNumber)->exists()) {
                    throw ValidationException::withMessages(['case_number' => 'This case number already exists in the Secretariat office.']);
                }
                $caseNumber = $requestedNumber;
            } else {
                $caseNumber = $this->numbers->allocateFamily($lockedOffice, 'CASE')['number'];
            }

            $case = SecretariatCase::query()->create([
                'office_id' => $lockedOffice->id,
                'case_number' => $caseNumber,
                'title' => $title,
                'summary' => $attributes['summary'] ?? null,
                'status' => 'open',
                'confidentiality' => $confidentiality,
                'created_by' => $actor->id,
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            $this->audit->append($lockedOffice, null, $actor, 'case_created', [
                'case_id' => $case->id,
                'case_number' => $case->case_number,
            ]);

            return $case;
        });
    }

    public function addRecord(SecretariatCase $case, SecretariatRecord $record, User $actor, string $role = 'related'): SecretariatCase
    {
        return $this->attachRecord($case, $record, $actor, $role, false);
    }

    public function addCrossOfficeReference(SecretariatCase $case, SecretariatRecord $record, User $actor, string $role = 'related'): SecretariatCase
    {
        return $this->attachRecord($case, $record, $actor, $role, true);
    }

    private function attachRecord(
        SecretariatCase $case,
        SecretariatRecord $record,
        User $actor,
        string $role,
        bool $crossOffice,
    ): SecretariatCase {
        return DB::transaction(function () use ($case, $record, $actor, $role, $crossOffice) {
            $lockedCase = SecretariatCase::query()->with('office')->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $lockedRecord = SecretariatRecord::query()->with('office')->whereKey($record->id)->lockForUpdate()->firstOrFail();

            if ($lockedCase->status === 'archived') {
                throw ValidationException::withMessages(['case' => 'Archived Secretariat cases cannot receive new records.']);
            }
            if ($lockedRecord->registry_number === null) {
                throw ValidationException::withMessages(['record' => 'Only formally registered Secretariat records can enter a case.']);
            }

            $sameOffice = (int) $lockedCase->office_id === (int) $lockedRecord->office_id;
            if ($crossOffice && $sameOffice) {
                throw ValidationException::withMessages(['record' => 'Use ordinary case membership for a record from the same office.']);
            }
            if (! $crossOffice && ! $sameOffice) {
                throw ValidationException::withMessages(['record' => 'Cross-office records require the explicit reference operation.']);
            }

            $existing = DB::table('secretariat_case_records')
                ->where('case_id', $lockedCase->id)
                ->where('record_id', $lockedRecord->id)
                ->first();

            if ($existing !== null) {
                return $lockedCase->load('records');
            }

            $linkType = $crossOffice ? 'cross_office_reference' : 'local_membership';
            DB::table('secretariat_case_records')->insert([
                'case_id' => $lockedCase->id,
                'record_id' => $lockedRecord->id,
                'link_type' => $linkType,
                'source_office_id' => $lockedRecord->office_id,
                'role' => $role,
                'added_by' => $actor->id,
                'added_at' => now(),
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($crossOffice) {
                // Destination audit does not pretend that the foreign record belongs
                // to the Case office. Source audit separately records the fact that
                // its official record is referenced by another office.
                $this->audit->append($lockedCase->office, null, $actor, 'cross_office_case_reference_added', [
                    'case_id' => $lockedCase->id,
                    'record_id' => $lockedRecord->id,
                    'source_office_id' => $lockedRecord->office_id,
                    'role' => $role,
                ]);
                $this->audit->append($lockedRecord->office, $lockedRecord, $actor, 'record_referenced_by_foreign_case', [
                    'case_id' => $lockedCase->id,
                    'destination_office_id' => $lockedCase->office_id,
                    'role' => $role,
                ]);
            } else {
                $this->audit->append($lockedCase->office, $lockedRecord, $actor, 'case_record_added', [
                    'case_id' => $lockedCase->id,
                    'role' => $role,
                ]);
            }

            return $lockedCase->load('records');
        });
    }

    public function transition(SecretariatCase $case, string $to, User $actor): SecretariatCase
    {
        if (! in_array($to, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported Secretariat case status.']);
        }

        return DB::transaction(function () use ($case, $to, $actor) {
            $locked = SecretariatCase::query()->with('office')->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $from = (string) $locked->status;
            if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Unsupported Secretariat case transition {$from} → {$to}."]);
            }

            $locked->performControlledMutation(function (SecretariatCase $target) use ($to, $actor): void {
                $target->status = $to;
                $target->closed_by = in_array($to, ['closed', 'archived'], true) ? $actor->id : null;
                $target->closed_at = in_array($to, ['closed', 'archived'], true) ? now() : null;
                $target->save();
            });

            $this->audit->append($locked->office, null, $actor, 'case_status_changed', [
                'case_id' => $locked->id,
                'from' => $from,
                'to' => $to,
            ]);

            return $locked->refresh();
        });
    }
}
