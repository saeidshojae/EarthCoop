<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('najm_transactions')) {
            return;
        }

        if (! Schema::hasColumn('najm_transactions', 'idempotency_key')) {
            Schema::table('najm_transactions', function (Blueprint $table) {
                $table->string('idempotency_key', 191)->nullable()->after('tracking_number');
            });
        }

        // Preserve production upgrade safety. Backfill only legacy keys that are
        // already unique. Duplicate historical keys stay NULL in the canonical
        // column so the migration can complete; the production-readiness audit
        // reports those duplicates explicitly and blocks release readiness.
        $counts = [];

        DB::table('najm_transactions')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(500, function ($transactions) use (&$counts) {
                foreach ($transactions as $transaction) {
                    $key = $this->metadataIdempotencyKey($transaction->metadata ?? null);
                    if ($key !== null) {
                        $counts[$key] = ($counts[$key] ?? 0) + 1;
                    }
                }
            });

        DB::table('najm_transactions')
            ->select(['id', 'metadata', 'idempotency_key'])
            ->whereNull('idempotency_key')
            ->orderBy('id')
            ->chunkById(500, function ($transactions) use ($counts) {
                foreach ($transactions as $transaction) {
                    $key = $this->metadataIdempotencyKey($transaction->metadata ?? null);
                    if ($key !== null && ($counts[$key] ?? 0) === 1) {
                        DB::table('najm_transactions')
                            ->where('id', $transaction->id)
                            ->update(['idempotency_key' => $key]);
                    }
                }
            });

        Schema::table('najm_transactions', function (Blueprint $table) {
            $table->unique('idempotency_key', 'najm_transactions_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('najm_transactions')
            || ! Schema::hasColumn('najm_transactions', 'idempotency_key')) {
            return;
        }

        Schema::table('najm_transactions', function (Blueprint $table) {
            $table->dropUnique('najm_transactions_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }

    private function metadataIdempotencyKey(mixed $metadata): ?string
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($metadata)) {
            return null;
        }

        $key = $metadata['idempotency_key'] ?? null;
        if (! is_string($key)) {
            return null;
        }

        $key = trim($key);
        return $key !== '' ? mb_substr($key, 0, 191) : null;
    }
};
