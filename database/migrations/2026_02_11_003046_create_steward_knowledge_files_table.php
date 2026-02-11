<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('steward_knowledge_files', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('original_filename', 255);
            $table->string('file_path', 500);
            $table->string('file_type', 50); // pdf, docx, txt, md, etc
            $table->integer('file_size'); // in bytes
            $table->text('extracted_content')->nullable();
            $table->text('summary')->nullable(); // خلاصه AI-generated
            $table->boolean('is_active')->default(true);
            $table->integer('search_priority')->default(5); // 1-10 برای وزن‌دهی در جستجو
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('is_active');
            $table->index('file_type');
            $table->index('created_at');
            $table->fullText(['title', 'extracted_content']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('steward_knowledge_files');
    }
};
