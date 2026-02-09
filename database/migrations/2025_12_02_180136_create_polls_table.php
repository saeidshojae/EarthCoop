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
        if (Schema::hasTable('polls')) {
            return;
        }
        
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            if (Schema::hasTable('groups')) {
                $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('group_id');
                $table->index('group_id');
            }
            
            if (Schema::hasTable('users')) {
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('created_by');
                $table->index('created_by');
            }
            
            if (Schema::hasTable('experience_fields')) {
                $table->foreignId('skill_id')->nullable()->constrained('experience_fields')->onDelete('set null');
            } else {
                $table->unsignedBigInteger('skill_id')->nullable();
                $table->index('skill_id');
            }
            
            // Content
            $table->string('question');
            
            // Settings
            $table->boolean('is_multiple')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_results')->default(true);
            
            // Types
            $table->integer('type')->default(0);
            $table->integer('main_type')->default(0);
            
            // Expiration
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('group_id');
            $table->index('created_by');
            $table->index('expires_at');
        });
        
        // Create poll_options table
        if (!Schema::hasTable('poll_options')) {
            Schema::create('poll_options', function (Blueprint $table) {
                $table->id();
                
                if (Schema::hasTable('polls')) {
                    $table->foreignId('poll_id')->constrained('polls')->onDelete('cascade');
                } else {
                    $table->unsignedBigInteger('poll_id');
                    $table->index('poll_id');
                }
                
                $table->string('text');
                
                $table->index('poll_id');
            });
        }
        
        // Create poll_votes table
        if (!Schema::hasTable('poll_votes')) {
            Schema::create('poll_votes', function (Blueprint $table) {
                $table->id();
                
                if (Schema::hasTable('polls')) {
                    $table->foreignId('poll_id')->constrained('polls')->onDelete('cascade');
                } else {
                    $table->unsignedBigInteger('poll_id');
                    $table->index('poll_id');
                }
                
                if (Schema::hasTable('poll_options')) {
                    $table->foreignId('option_id')->constrained('poll_options')->onDelete('cascade');
                } else {
                    $table->unsignedBigInteger('option_id');
                    $table->index('option_id');
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                } else {
                    $table->unsignedBigInteger('user_id');
                    $table->index('user_id');
                }
                
                $table->timestamps();
                
                // Indexes
                $table->index('poll_id');
                $table->index('option_id');
                $table->index('user_id');
                
                // Unique constraint: یک کاربر نمی‌تواند دو بار به یک گزینه رأی دهد
                $table->unique(['poll_id', 'user_id', 'option_id']);
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
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
    }
};
