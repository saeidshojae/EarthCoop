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
        if (!Schema::hasTable('reactions')) {
            Schema::create('reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blog_id')->nullable()->constrained('blogs')->onDelete('cascade');
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
        } else {
            // اگر جدول وجود دارد، فقط ستون‌های مفقود را اضافه کن
            Schema::table('reactions', function (Blueprint $table) {
                if (!Schema::hasColumn('reactions', 'blog_id')) {
                    $table->foreignId('blog_id')->nullable()->constrained('blogs')->onDelete('cascade')->after('id');
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
