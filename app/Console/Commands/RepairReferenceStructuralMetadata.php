<?php

namespace App\Console\Commands;

use App\Data\LocationGovernance\ReferenceDataset;
use App\Models\LocationSchema;
use App\Models\LocationSchemaType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RepairReferenceStructuralMetadata extends Command
{
    protected $signature = 'location-governance:repair-reference-structural-metadata
        {--apply : Apply only the missing or divergent structural metadata keys}
        {--confirm= : Required exact confirmation when applying}';

    protected $description = 'Dry-run or safely reconcile IR v1 reference structural schema metadata without importing locations or changing governance data.';

    public function handle(): int
    {
        $confirmation = 'APPLY-REFERENCE-STRUCTURAL-METADATA-IR-v1';
        if ($this->option('apply') && $this->option('confirm') !== $confirmation) {
            $this->error('Apply refused. Pass --confirm='.$confirmation.' after reviewing the dry-run.');
            return self::FAILURE;
        }

        try {
            $definition = ReferenceDataset::fromCountryVersion('IR', 'v1')->schema;
            if (($definition['key'] ?? null) !== 'ir-reference-v1' || ($definition['country_code'] ?? null) !== 'IR') {
                throw new RuntimeException('Unexpected IR reference schema identity.');
            }

            $schema = LocationSchema::query()
                ->where('key', 'ir-reference-v1')
                ->where('country_code', 'IR')
                ->firstOrFail();
            if (! in_array((string) $schema->version, ['1', 'v1'], true)) {
                throw new RuntimeException('Unexpected installed reference schema version; no changes applied.');
            }

            $changes = DB::transaction(function () use ($schema, $definition): array {
                $pending = [];
                foreach ($definition['types'] as $type) {
                    $expected = array_intersect_key(
                        $type['metadata'] ?? [],
                        array_flip(['structural_claim_types', 'structural_claim_types_after'])
                    );
                    if ($expected === []) {
                        continue;
                    }

                    $pivot = LocationSchemaType::query()
                        ->where('location_schema_id', $schema->id)
                        ->whereHas('type', fn ($query) => $query->where('key', $type['key']))
                        ->lockForUpdate()
                        ->first();
                    if ($pivot === null) {
                        throw new RuntimeException('Missing schema/type mapping: '.$type['key'].'; no changes applied.');
                    }

                    $current = $pivot->metadata ?? [];
                    $different = [];
                    foreach ($expected as $key => $value) {
                        if (! array_key_exists($key, $current) || $current[$key] !== $value) {
                            $different[] = $key;
                            $current[$key] = $value;
                        }
                    }

                    if ($different !== []) {
                        $pending[] = ['type' => $type['key'], 'keys' => $different];
                        if ($this->option('apply')) {
                            // Preserve all unrelated metadata and all existing location/governance rows.
                            $pivot->forceFill(['metadata' => $current])->save();
                        }
                    }
                }
                return $pending;
            });

            if ($changes === []) {
                $this->info('Structural reference metadata is already aligned; no changes needed.');
                return self::SUCCESS;
            }

            foreach ($changes as $change) {
                $this->line($change['type'].': '.implode(', ', $change['keys']));
            }
            $this->info($this->option('apply')
                ? 'Structural metadata updated. No locations or governance records were changed.'
                : 'Dry-run only. No data changed; review and rerun with --apply and the exact confirmation to apply.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Structural metadata repair stopped: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
