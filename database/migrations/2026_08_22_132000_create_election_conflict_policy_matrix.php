<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_conflict_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->dateTime('effective_at');
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason', 500);
            $table->timestamps();
            $table->index(['effective_at', 'retired_at'], 'election_conflict_policy_effective_index');
        });

        Schema::create('election_conflict_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_version_id')->constrained('election_conflict_policy_versions')->cascadeOnDelete();
            $table->string('current_position', 24);
            $table->string('current_domain_type', 24);
            $table->string('current_level', 32);
            $table->string('new_position', 24);
            $table->string('new_domain_type', 24);
            $table->string('new_level', 32);
            $table->string('decision', 32);
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->unique([
                'policy_version_id', 'current_position', 'current_domain_type', 'current_level',
                'new_position', 'new_domain_type', 'new_level',
            ], 'election_conflict_rule_matrix_unique');
        });

        $now = now();
        $versionId = DB::table('election_conflict_policy_versions')->insertGetId([
            'version' => 1,
            'effective_at' => $now,
            'change_reason' => 'E0 baseline: public responsibility versus same-or-higher specialized responsibility',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $levels = [
            'alley' => 0, 'street' => 1, 'neighborhood' => 2,
            'region' => 3, 'village' => 3, 'city' => 4, 'rural' => 4,
            'section' => 5, 'district' => 5, 'county' => 6, 'province' => 7,
            'country' => 8, 'continent' => 9, 'global' => 10,
        ];
        $rows = [];
        foreach (['manager', 'inspector'] as $currentPosition) {
            foreach ($levels as $currentLevel => $currentRank) {
                foreach (['manager', 'inspector'] as $newPosition) {
                    foreach (['job', 'experience'] as $newDomain) {
                        foreach ($levels as $newLevel => $newRank) {
                            if ($newRank < $currentRank) {
                                continue;
                            }
                            $rows[] = [
                                'policy_version_id' => $versionId,
                                'current_position' => $currentPosition,
                                'current_domain_type' => 'public',
                                'current_level' => $currentLevel,
                                'new_position' => $newPosition,
                                'new_domain_type' => $newDomain,
                                'new_level' => $newLevel,
                                'decision' => 'allowed_with_suspension',
                                'reason' => 'E0 baseline conflict rule',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('election_conflict_policy_rules')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('election_conflict_policy_rules');
        Schema::dropIfExists('election_conflict_policy_versions');
    }
};
