<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election_tally_results', function (Blueprint $table) {
            $table->string('cycle_identifier', 96)->after('within_seat_cutoff');
            $table->timestamp('stopped_at')->after('cycle_identifier');
            $table->char('vote_snapshot_hash', 64)->after('stopped_at');
            $table->string('draw_seed_version', 64)->after('vote_snapshot_hash');
            $table->char('draw_seed', 64)->after('draw_seed_version');

            $table->index(
                ['election_id', 'cycle_identifier'],
                'election_tally_results_cycle_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('election_tally_results', function (Blueprint $table) {
            $table->dropIndex('election_tally_results_cycle_index');
            $table->dropColumn([
                'cycle_identifier',
                'stopped_at',
                'vote_snapshot_hash',
                'draw_seed_version',
                'draw_seed',
            ]);
        });
    }
};
