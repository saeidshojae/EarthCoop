<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('moderation_case_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('classification', 60)->default('other');
            $table->string('severity', 20)->default('medium');
            $table->text('summary');
            $table->string('status', 20)->default('draft');
            $table->string('reason_code', 100)->nullable();
            $table->timestamps();
            $table->unique(['source_type','source_id','status'], 'moderation_case_summary_open_unique');
            $table->index(['severity','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_case_summaries');
    }
};
