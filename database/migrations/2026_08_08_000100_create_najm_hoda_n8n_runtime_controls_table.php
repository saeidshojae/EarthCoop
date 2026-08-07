<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_hoda_n8n_runtime_controls', function (Blueprint $table): void {
            $table->id();
            $table->boolean('outbound_enabled')->default(true);
            $table->boolean('callback_ingress_enabled')->default(true);
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_hoda_n8n_runtime_controls');
    }
};
