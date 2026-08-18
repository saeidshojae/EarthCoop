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

    public function __construct(private readonly SecretariatAuditService $audit)
    {
    }

    /** @param array<string,mixed> $attributes */
    public function create(SecretariatOffice $office, User $actor, array $attributes): SecretariatCase
    {
        $title = trim((string) ($attributes['title'] ?? ''));
        $confidentiality = (string) ($attributes['confidentiality'] ?? $office->default_confidentiality ?? 'office_members');
        if ($title === '' || mb_strlen($title) > 500) {
            throw ValidationException::withMessages(['title' => 'A Secretariat case requires a title up to 500 characters.']);
        }
        if (! in_array($confidentiality, self::CONFIDENTIALITIES, true)) {
            throw ValidationException::withMessages(['confidentiality' => 'Unsupported case confidentiality.']);
        }

        return DB::transaction(function () use ($office, $actor, $attributes, $title, $confidentiality) {
            $lockedOffice = SecretariatOffice::query()->whereKey($office->id)->lockForUpdate()->firstOrFail();
            $next = (int) SecretariatCase::query()->where('office_id', $lockedOffice->id)->max('id') + 1;
            $caseNumber = $attributes['case_number'] ?? sprintf('%s/CASE/%06d', $lockedOffice->code, $next);

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
        return DB::transaction(function () use ($case, $record, $actor, $role) {
            $lockedCase = SecretariatCase::query()->with('office')->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $lockedRecord = SecretariatRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

            if ($lockedCase->status === 'archived') {
                throw ValidationException::withMessages(['case' => 'Archived Secretariat cases cannot receive new records.']);
            }
            if ((int) $lockedCase->office_id !== (int) $lockedRecord->office_id) {
                throw ValidationException::withMessages(['record' => 'S5 starts with same-office case membership; cross-office references require explicit routing policy.']);
            }
            if ($lockedRecord->registry_number === null) {
                throw ValidationException::withMessages(['record' => 'Only formally registered Secretariat records can enter a case.']);
            }

            $existing = DB::table('secretariat_case_records')
                ->where('case_id', $lockedCase->id)
                ->where('record_id', $lockedRecord->id)
                ->first();

            if ($existing !== null) {
                return $lockedCase->load('records');
            }

            DB::table('secretariat_case_records')->insert([
                'case_id' => $lockedCase->id,
                'record_id' => $lockedRecord->id,
                'role' => $role,
                'added_by' => $actor->id,
                'added_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->append($lockedCase->office, $lockedRecord, $actor, 'case_record_added', [
                'case_id' => $lockedCase->id,
                'role' => $role,
            ]);

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
