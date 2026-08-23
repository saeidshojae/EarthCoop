<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_setting_id')->constrained('group_setting')->cascadeOnDelete();
            $table->string('level_key', 128);
            $table->unsignedInteger('version');
            $table->boolean('election_status')->default(true);
            $table->unsignedInteger('manager_count');
            $table->unsignedInteger('inspector_count');
            $table->unsignedInteger('voting_duration_days');
            $table->unsignedInteger('start_threshold');
            $table->unsignedInteger('cycle_interval_months');
            $table->unsignedInteger('response_duration_days')->default(7);
            $table->timestamp('effective_at');
            $table->timestamp('retired_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('change_reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['group_setting_id', 'version'], 'election_policy_versions_setting_version_unique');
            $table->index(['group_setting_id', 'effective_at', 'retired_at'], 'election_policy_versions_effective_index');
            $table->index(['level_key', 'effective_at'], 'election_policy_versions_level_effective_index');
        });

        Schema::table('elections', function (Blueprint $table) {
            $table->foreignId('policy_version_id')->nullable()->after('previous_election_id')
                ->constrained('election_policy_versions')->nullOnDelete();
            $table->index(['group_id', 'policy_version_id'], 'elections_group_policy_version_index');
        });

        $now = now();
        $policyByLevel = [];
        foreach (DB::table('group_setting')->orderBy('id')->get() as $setting) {
            $policyByLevel[$setting->level] = DB::table('election_policy_versions')->insertGetId([
                'group_setting_id' => $setting->id,
                'level_key' => $setting->level,
                'version' => 1,
                'election_status' => (bool) $setting->election_status,
                'manager_count' => max(0, (int) $setting->manager_count),
                'inspector_count' => max(0, (int) $setting->inspector_count),
                'voting_duration_days' => max(1, (int) $setting->election_time),
                'start_threshold' => max(1, (int) $setting->max_for_election),
                'cycle_interval_months' => max(0, (int) $setting->second_election_time),
                'response_duration_days' => 7,
                'effective_at' => $setting->created_at ?? $now,
                'created_by' => null,
                'change_reason' => 'legacy_group_setting_baseline',
                'metadata' => json_encode(['source' => 'group_setting_backfill'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('elections')->orderBy('id')->chunkById(500, function ($elections) use ($policyByLevel): void {
            foreach ($elections as $election) {
                $group = DB::table('groups')->where('id', $election->group_id)->first();
                if ($group === null) {
                    continue;
                }

                $base = (string) $group->location_level;
                $levelKey = match (true) {
                    $group->specialty_id !== null => $base.'_job',
                    $group->experience_id !== null => $base.'_experience',
                    $group->age_group_id !== null => $base.'_age',
                    $group->gender !== null => $base.'_gender',
                    default => $base,
                };

                $policyId = $policyByLevel[$levelKey] ?? null;
                if ($policyId !== null) {
                    DB::table('elections')->where('id', $election->id)->update(['policy_version_id' => $policyId]);
                }
            }
        }, 'id');
    }

    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->dropForeign(['policy_version_id']);
            $table->dropIndex('elections_group_policy_version_index');
            $table->dropColumn('policy_version_id');
        });
        Schema::dropIfExists('election_policy_versions');
    }
};
