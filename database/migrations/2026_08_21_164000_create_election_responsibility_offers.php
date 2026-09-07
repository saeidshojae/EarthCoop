<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_responsibility_contract_versions', function (Blueprint $table) {
            $table->id();
            $table->string('position', 24);
            $table->unsignedInteger('version');
            $table->longText('body');
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['position', 'version'], 'election_contract_position_version_unique');
            $table->index(['position', 'is_active', 'published_at'], 'election_contract_active_index');
        });

        Schema::create('election_responsibility_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('candidate_user_id');
            $table->string('position', 24);
            $table->unsignedInteger('ranking_position');
            $table->unsignedBigInteger('contract_version_id');
            $table->string('status', 24)->default('pending');
            // DATETIME keeps these fields required while avoiding legacy MySQL's
            // implicit zero-TIMESTAMP default behavior for multiple NOT NULL timestamps.
            $table->dateTime('offered_at');
            $table->dateTime('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('eligibility_checked_at')->nullable();
            $table->string('resolution_reason', 96)->nullable();
            $table->json('response_metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['election_id', 'position', 'candidate_user_id'],
                'election_responsibility_offer_candidate_unique'
            );
            $table->index(['election_id', 'position', 'status'], 'election_responsibility_offer_queue_index');
            $table->index(['status', 'expires_at'], 'election_responsibility_offer_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_responsibility_offers');
        Schema::dropIfExists('election_responsibility_contract_versions');
    }
};
