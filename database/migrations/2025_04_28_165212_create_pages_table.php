<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->longText('title_translations')->nullable();

            $table->string('slug')->unique();

            $table->string('template')->default('default');

            $table->text('content');
            $table->longText('content_translations')->nullable();

            $table->string('meta_title')->nullable();
            $table->longText('meta_title_translations')->nullable();

            $table->text('meta_description')->nullable();
            $table->longText('meta_description_translations')->nullable();

            $table->boolean('is_published')->default(false);
            $table->boolean('show_in_header')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};