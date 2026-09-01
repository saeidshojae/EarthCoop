<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election_vacancies', function (Blueprint $table) {
            $table->string('continuity_mode', 24)->default('immediate')->after('position');
            $table->index(['continuity_mode', 'status'], 'election_vacancies_continuity_status_index');
        });

        Schema::table('elections', function (Blueprint $table) {
            $table->unsignedInteger('cycle_number')->nullable()->after('group_id');
            $table->foreignId('previous_election_id')->nullable()->after('cycle_number')
                ->constrained('elections')->nullOnDelete();
            $table->index(['group_id', 'cycle_number'], 'elections_group_cycle_number_index');
        });

        DB::table('elections')
            ->select('group_id')
            ->distinct()
            ->orderBy('group_id')
            ->pluck('group_id')
            ->each(function ($groupId): void {
                $previousId = null;
                $cycle = 1;
                DB::table('elections')
                    ->where('group_id', $groupId)
                    ->orderBy('id')
                    ->get(['id'])
                    ->each(function ($row) use (&$previousId, &$cycle): void {
                        DB::table('elections')->where('id', $row->id)->update([
                            'cycle_number' => $cycle++,
                            'previous_election_id' => $previousId,
                        ]);
                        $previousId = $row->id;
                    });
            });
    }

    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->dropIndex('elections_group_cycle_number_index');
            $table->dropConstrainedForeignId('previous_election_id');
            $table->dropColumn('cycle_number');
        });

        Schema::table('election_vacancies', function (Blueprint $table) {
            $table->dropIndex('election_vacancies_continuity_status_index');
            $table->dropColumn('continuity_mode');
        });
    }
};
