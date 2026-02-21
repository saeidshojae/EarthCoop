<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_hoda_runtime_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 120);
            $table->string('request_id', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
            $table->index(['request_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_hoda_runtime_events');
    }
};

