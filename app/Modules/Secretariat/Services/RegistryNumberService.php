<?php

namespace App\Modules\Secretariat\Services;

use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatSequence;
use Illuminate\Support\Facades\DB;

class RegistryNumberService
{
    public function familyFor(string $recordType): string
    {
        return match ($recordType) {
            'incoming_letter', 'outgoing_letter', 'internal_correspondence' => 'COR',
            'meeting_minute' => 'MIN',
            'resolution', 'formal_decision' => 'GOV',
            'contract', 'memorandum_of_understanding', 'agreement' => 'CON',
            'policy', 'directive' => 'POL',
            'official_report', 'execution_record', 'financial_record' => 'REP',
            'election_record' => 'ELC',
            'case_record' => 'CAS',
            default => 'GEN',
        };
    }

    /**
     * Must be called inside the caller's transaction.
     *
     * Registry numbering is a serialized namespace operation per office. We lock
     * the office row first so every allocator for that office acquires locks in
     * the same order before touching a sequence row. This avoids the InnoDB
     * insert-or-ignore/SELECT FOR UPDATE deadlock that can otherwise occur when
     * many requests race to create the first sequence row for the same scope.
     *
     * @return array{year:int,family:string,sequence:int,number:string}
     */
    public function allocate(SecretariatOffice $office, string $recordType, ?int $year = null): array
    {
        $year ??= (int) now()->year;
        $family = $this->familyFor($recordType);
        $now = now();

        // The office is the namespace root. Lock it before any sequence lookup or
        // creation so concurrent allocations for the same office cannot acquire
        // sequence locks in competing orders.
        SecretariatOffice::query()
            ->whereKey($office->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        DB::table('secretariat_sequences')->insertOrIgnore([
            'office_id' => $office->id,
            'calendar_year' => $year,
            'record_family' => $family,
            'last_value' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var SecretariatSequence $sequence */
        $sequence = SecretariatSequence::query()
            ->where('office_id', $office->id)
            ->where('calendar_year', $year)
            ->where('record_family', $family)
            ->lockForUpdate()
            ->firstOrFail();

        $next = (int) $sequence->last_value + 1;
        $sequence->forceFill(['last_value' => $next])->save();

        return [
            'year' => $year,
            'family' => $family,
            'sequence' => $next,
            'number' => $this->format($office, $year, $family, $next),
        ];
    }

    public function format(SecretariatOffice $office, int $year, string $family, int $sequence): string
    {
        $policy = $office->numbering_policy ?? [];
        $template = (string) ($policy['format'] ?? '{OFFICE}/{YEAR}/{FAMILY}/{SEQ}');
        $width = max(1, min(12, (int) ($policy['sequence_width'] ?? 6)));

        return strtr($template, [
            '{OFFICE}' => $office->code,
            '{YEAR}' => (string) $year,
            '{FAMILY}' => $family,
            '{SEQ}' => str_pad((string) $sequence, $width, '0', STR_PAD_LEFT),
        ]);
    }
}
