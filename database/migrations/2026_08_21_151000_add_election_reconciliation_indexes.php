<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->index(['election_id', 'voter_id'], 'votes_election_voter_index');
            $table->index(
                ['election_id', 'candidate_user_id', 'position'],
                'votes_election_candidate_position_index'
            );
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->index(['election_id', 'user_id'], 'candidates_election_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex('votes_election_voter_index');
            $table->dropIndex('votes_election_candidate_position_index');
        });

        // On MySQL/InnoDB the composite reconciliation index may become the
        // supporting index selected for the existing candidates.election_id FK.
        // Dropping it directly then fails with errno 1553. Install a narrow,
        // legacy-safe supporting index first; keeping that index after rollback
        // is intentionally non-destructive and makes re-apply safe.
        if (DB::getDriverName() === 'mysql'
            && ! $this->mysqlIndexExists('candidates', 'candidates_election_id_rollback_index')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->index('election_id', 'candidates_election_id_rollback_index');
            });
        }

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex('candidates_election_user_index');
        });
    }

    private function mysqlIndexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
