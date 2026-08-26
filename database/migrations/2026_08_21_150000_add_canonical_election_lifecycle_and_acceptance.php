<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsLifecycle = ! Schema::hasColumn('elections', 'lifecycle_status');
        if ($needsLifecycle) {
            Schema::table('elections', function (Blueprint $table) {
                $table->string('lifecycle_status', 32)->nullable()->after('is_closed');
                $table->index('lifecycle_status', 'elections_lifecycle_status_index');
            });
        }

        $needsAcceptance = ! Schema::hasColumn('candidates', 'acceptance_status');
        if ($needsAcceptance) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->string('acceptance_status', 32)->nullable()->after('accept_status');
                $table->index('acceptance_status', 'candidates_acceptance_status_index');
            });
        }

        // The legacy migration declared accept_status as ENUM(accepted,declined),
        // while deployed application code historically wrote 0/1/2 as well.
        // Widening it to VARCHAR preserves every legacy representation during
        // the compatibility window instead of coercing or rejecting values.
        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('candidates', 'accept_status')) {
            DB::statement('ALTER TABLE `candidates` MODIFY `accept_status` VARCHAR(32) NULL');
        }

        $this->backfillLifecycleStatus();
        $this->backfillAcceptanceStatus();
    }

    public function down(): void
    {
        // Canonical columns are safe to remove because legacy columns remain.
        if (Schema::hasColumn('candidates', 'acceptance_status')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->dropIndex('candidates_acceptance_status_index');
                $table->dropColumn('acceptance_status');
            });
        }

        if (Schema::hasColumn('elections', 'lifecycle_status')) {
            Schema::table('elections', function (Blueprint $table) {
                $table->dropIndex('elections_lifecycle_status_index');
                $table->dropColumn('lifecycle_status');
            });
        }

        // Do not narrow legacy accept_status back to ENUM on rollback: numeric
        // legacy values may exist and narrowing would be destructive.
    }

    private function backfillLifecycleStatus(): void
    {
        $now = now();

        DB::table('elections')
            ->whereNull('lifecycle_status')
            ->orderBy('id')
            ->chunkById(500, function ($elections) use ($now): void {
                foreach ($elections as $election) {
                    $status = 'open';

                    if ((bool) $election->is_closed) {
                        $status = 'closed';
                    } elseif ($election->starts_at !== null && $now->lt($election->starts_at)) {
                        $status = 'scheduled';
                    } elseif ($election->ends_at !== null && $now->gte($election->ends_at)) {
                        $status = 'closed';
                    }

                    DB::table('elections')
                        ->where('id', $election->id)
                        ->update(['lifecycle_status' => $status]);
                }
            }, 'id');
    }

    private function backfillAcceptanceStatus(): void
    {
        DB::table('candidates')
            ->whereNull('acceptance_status')
            ->orderBy('id')
            ->chunkById(500, function ($candidates): void {
                foreach ($candidates as $candidate) {
                    $raw = $candidate->accept_status;
                    $canonical = match ((string) $raw) {
                        '1', 'pending' => 'pending',
                        '2', 'accepted' => 'accepted',
                        'declined' => 'declined',
                        'expired' => 'expired',
                        default => null,
                    };

                    // Legacy 0 is deliberately not interpreted. It historically
                    // meant both "declined after an offer" and "not offered".
                    if ($canonical !== null) {
                        DB::table('candidates')
                            ->where('id', $candidate->id)
                            ->update(['acceptance_status' => $canonical]);
                    }
                }
            }, 'id');
    }
};
