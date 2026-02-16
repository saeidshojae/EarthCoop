<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('najm_bahar_investments')) {
            return;
        }

        Schema::create('najm_bahar_investments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->morphs('investor');
            $table->bigInteger('amount');
            $table->decimal('agreed_profit_percentage', 5, 2)->nullable();
            $table->bigInteger('expected_return')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('transaction_tracking')->nullable();
            $table->enum('status', [
                'pending',
                'paid',
                'active',
                'completed',
                'cancelled',
                'refunded',
            ])->default('pending');
            $table->timestamp('invested_at')->nullable();
            $table->timestamp('maturity_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id')->references('id')->on('najm_bahar_projects')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('najm_transactions')->onDelete('set null');
            $table->index('status');
            $table->index(['project_id', 'status']);
            $table->index('invested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_investments');
    }
};
