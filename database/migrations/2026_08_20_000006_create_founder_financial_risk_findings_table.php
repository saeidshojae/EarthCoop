<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('founder_financial_risk_findings', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 32)->index();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('risk_code', 96)->index();
            $table->string('severity', 16)->default('medium')->index();
            $table->string('status', 24)->default('open')->index();
            $table->string('summary', 500);
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['domain','entity_type','entity_id','risk_code'],'founder_financial_risk_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('founder_financial_risk_findings'); }
};
