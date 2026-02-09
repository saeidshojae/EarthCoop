<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('title');
            $table->string('category')->nullable();
            $table->text('question');
            $table->longText('answer')->nullable();
            $table->string('status')->default('new');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['status', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_questions');
    }
};



