<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_hoda_n8n_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 128)->unique();
            $table->string('correlation_id', 128)->index();
            $table->string('workflow', 191)->index();
            $table->string('mode', 32);
            $table->string('status', 32)->index();
            $table->string('remote_run_id', 191)->index();
            $table->json('result')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['remote_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_hoda_n8n_callbacks');
    }
};
