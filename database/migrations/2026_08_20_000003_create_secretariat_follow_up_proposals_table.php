<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('secretariat_follow_up_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained('secretariat_dispatches')->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('urgency', 20)->default('normal');
            $table->text('proposal');
            $table->string('reason_code', 100)->nullable();
            $table->timestamps();
            $table->unique(['dispatch_id','status'], 'secretariat_followup_open_unique');
            $table->index(['urgency','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_follow_up_proposals');
    }
};
