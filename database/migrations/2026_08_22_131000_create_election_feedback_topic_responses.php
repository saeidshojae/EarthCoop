<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_feedback_topic_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('topic_key', 64);
            $table->date('aggregation_window_start');
            $table->date('aggregation_window_end');
            $table->unsignedInteger('aggregate_count');
            $table->unsignedInteger('min_distinct_authors');
            $table->unsignedInteger('min_bucket_days');
            $table->text('body');
            $table->string('status', 24)->default('published');
            $table->dateTime('published_at');
            $table->timestamps();

            $table->index(['election_id', 'subject_user_id', 'topic_key'], 'election_topic_response_subject_topic_index');
            $table->index(['election_id', 'status', 'published_at'], 'election_topic_response_public_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_feedback_topic_responses');
    }
};
