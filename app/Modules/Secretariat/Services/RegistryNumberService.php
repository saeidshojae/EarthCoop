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
     * @return array{year:int,family:string,sequence:int,number:string}
     */
    public function allocate(SecretariatOffice $office, string $recordType, ?int $year = null): array
    {
        return $this->allocateFamily($office, $this->familyFor($recordType), $year);
    }

    /**
     * Allocate from a stable registry namespace family that is not necessarily a
     * SecretariatRecord taxonomy type. Cases use the dedicated CASE family while
     * ordinary records continue to map through familyFor().
     *
     * The office row is always locked first. This gives every allocation in the
     * office the same lock order before a sequence row is created/locked and is
     * the concurrency invariant already proven by the S1 registry probe.
     *
     * @return array{year:int,family:string,sequence:int,number:string}
     */
    public function allocateFamily(SecretariatOffice $office, string $family, ?int $year = null): array
    {
        $family = strtoupper(trim($family));
        if ($family === '' || ! preg_match('/^[A-Z0-9_-]{1,32}$/', $family)) {
            throw new \InvalidArgumentException('Registry sequence family must be a stable token up to 32 characters.');
        }

        $year ??= (int) now()->year;
        $now = now();

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
