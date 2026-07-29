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
        $blogTable = Schema::hasTable('blogs') ? 'blogs' : (Schema::hasTable('blog_posts') ? 'blog_posts' : null);

        if (!Schema::hasTable('reactions')) {
            Schema::create('reactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('blog_id')->nullable();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->unsignedBigInteger('comment_id')->nullable();
                $table->tinyInteger('type')->default(0)->comment('0=dislike, 1=like');
                $table->string('react_type')->nullable();
                $table->timestamps();

                // Indexes for better query performance
                $table->index('blog_id');
                $table->index('user_id');
                $table->index('comment_id');
                $table->index('type');
                // Unique constraint: یک کاربر فقط یک reaction برای هر blog/comment
                $table->unique(['user_id', 'blog_id', 'comment_id'], 'unique_user_reaction');
            });

            if ($blogTable !== null) {
                Schema::table('reactions', function (Blueprint $table) use ($blogTable) {
                    $table->foreign('blog_id')->references('id')->on($blogTable)->onDelete('cascade');
                });
            }
        } else {
            // اگر جدول وجود دارد، فقط ستون‌های مفقود را اضافه کن
            Schema::table('reactions', function (Blueprint $table) {
                if (!Schema::hasColumn('reactions', 'blog_id')) {
                    $table->unsignedBigInteger('blog_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn('reactions', 'user_id')) {
                    $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->after('blog_id');
                }
                if (!Schema::hasColumn('reactions', 'comment_id')) {
                    $table->unsignedBigInteger('comment_id')->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('reactions', 'type')) {
                    $table->tinyInteger('type')->default(0)->comment('0=dislike, 1=like')->after('comment_id');
                }
                if (!Schema::hasColumn('reactions', 'react_type')) {
                    $table->string('react_type')->nullable()->after('type');
                }
            });

            if ($blogTable !== null && Schema::hasColumn('reactions', 'blog_id')) {
                try {
                    Schema::table('reactions', function (Blueprint $table) use ($blogTable) {
                        $table->foreign('blog_id')->references('id')->on($blogTable)->onDelete('cascade');
                    });
                } catch (\Throwable $e) {
                    // Ignore if FK already exists.
                }
            }

            // Add indexes if they don't exist
            Schema::table('reactions', function (Blueprint $table) {
                $table->index('blog_id');
                $table->index('user_id');
                $table->index('comment_id');
                $table->index('type');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reactions');
    }
};
