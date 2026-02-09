<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_article_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('kb_tag_id')->constrained('kb_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kb_article_id', 'kb_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_tag');
    }
};




