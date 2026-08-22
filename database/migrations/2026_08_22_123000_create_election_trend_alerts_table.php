<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_trend_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('candidate_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('position', 24);
            $table->date('window_start');
            $table->date('window_end');
            $table->string('trend_direction', 16);
            $table->string('fingerprint', 64);
            $table->timestamp('notified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['election_id', 'candidate_user_id', 'position', 'window_start', 'window_end', 'trend_direction'],
                'election_trend_alert_dedup_unique'
            );
            $table->index(['candidate_user_id', 'notified_at'], 'election_trend_alert_candidate_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_trend_alerts');
    }
};
