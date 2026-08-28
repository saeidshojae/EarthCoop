<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('secretariat_offices')->restrictOnDelete();
            $table->unsignedInteger('calendar_year');
            $table->string('record_family', 48);
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
            $table->unique(['office_id', 'calendar_year', 'record_family'], 'secretariat_sequences_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_sequences');
    }
};
