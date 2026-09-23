<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_settlement_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reference_settlement_id');
            $table->foreignId('reviewed_by_user_id');
            $table->string('decision', 48);
            $table->text('reason');
            $table->string('evidence_source', 255)->nullable();
            $table->date('evidence_date')->nullable();
            $table->string('evidence_reference', 1000)->nullable();
            $table->json('snapshot');
            $table->timestamps();

            $table->foreign('reference_settlement_id', 'rs_review_settlement_fk')
                ->references('id')->on('reference_settlements')->restrictOnDelete();
            $table->foreign('reviewed_by_user_id', 'rs_review_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->index(['reference_settlement_id', 'id'], 'rs_review_history_idx');
            $table->index(['decision', 'created_at'], 'rs_review_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_settlement_reviews');
    }
};
