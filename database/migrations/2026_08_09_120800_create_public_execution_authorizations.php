<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_public_execution_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id');
            $table->foreignId('authorized_by');
            $table->string('status', 30)->default('authorized');
            $table->json('conditions')->nullable();
            $table->timestamp('authorized_at');
            $table->timestamps();

            $table->unique('plan_id', 'gov_pub_exec_plan_unique');
            $table->foreign('plan_id', 'gov_pub_exec_plan_fk')
                ->references('id')->on('governance_public_contribution_plans')->cascadeOnDelete();
            $table->foreign('authorized_by', 'gov_pub_exec_actor_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_public_execution_authorizations');
    }
};
