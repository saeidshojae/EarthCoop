<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('election_eligibility_snapshots')) {
            Schema::create('election_eligibility_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
                // Intentionally no user FK: an election snapshot is historical
                // evidence and must survive later user deletion/deactivation.
                $table->unsignedBigInteger('user_id');
                $table->boolean('voter_eligible')->default(false);
                $table->boolean('selectable_eligible')->default(false);
                $table->string('voter_exclusion_reason', 80)->nullable();
                $table->string('selectable_exclusion_reason', 80)->nullable();
                $table->integer('membership_role')->nullable();
                $table->integer('membership_status')->nullable();
                $table->string('snapshot_version', 32)->default('e4-v1');
                $table->timestamp('captured_at');
                $table->timestamps();

                $table->unique(['election_id', 'user_id'], 'election_eligibility_election_user_unique');
                $table->index(['election_id', 'voter_eligible'], 'election_eligibility_voter_index');
                $table->index(['election_id', 'selectable_eligible'], 'election_eligibility_selectable_index');
                $table->index('user_id', 'election_eligibility_user_index');
            });
        }

        if (! Schema::hasColumn('elections', 'eligibility_snapshot_captured_at')) {
            Schema::table('elections', function (Blueprint $table) {
                $table->timestamp('eligibility_snapshot_captured_at')->nullable()->after('lifecycle_status');
                $table->string('eligibility_snapshot_version', 32)->nullable()->after('eligibility_snapshot_captured_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('elections', 'eligibility_snapshot_captured_at')) {
            Schema::table('elections', function (Blueprint $table) {
                $table->dropColumn(['eligibility_snapshot_captured_at', 'eligibility_snapshot_version']);
            });
        }

        Schema::dropIfExists('election_eligibility_snapshots');
    }
};
