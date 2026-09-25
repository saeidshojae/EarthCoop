<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_settlement_residence_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reference_settlement_id');
            $table->foreignId('user_id');
            $table->string('status', 32)->default('pending')->index();
            $table->dateTime('submitted_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable();
            $table->foreign('reference_settlement_id', 'rsrc_settlement_fk')->references('id')->on('reference_settlements')->restrictOnDelete();
            $table->foreign('user_id', 'rsrc_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by_user_id', 'rsrc_reviewer_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['reference_settlement_id', 'user_id'], 'ref_settlement_claim_identity_unique');
            $table->index(['status', 'submitted_at'], 'ref_settlement_claim_review_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_settlement_residence_claims');
    }
};
