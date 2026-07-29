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
        Schema::create('messages', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('group_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->foreignId('thread_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->foreignId('edited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('removed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Message Content
            |--------------------------------------------------------------------------
            */

            $table->text('message')->nullable();

            $table->string('voice_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Attachments
            |--------------------------------------------------------------------------
            */

            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->string('file_name')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Client Synchronization
            |--------------------------------------------------------------------------
            */

            $table->string('client_message_id', 100)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Threading
            |--------------------------------------------------------------------------
            */

            $table->integer('reply_count')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('edited')->default(false);

            // موجود در دیتابیس Production
            $table->boolean('is_edited')->default(false);

            // موجود در دیتابیس Production
            $table->boolean('is_pinned')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Read Receipts
            |--------------------------------------------------------------------------
            */

            $table->longText('read_by')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->unique(
                ['group_id', 'user_id', 'client_message_id'],
                'messages_group_user_client_message_id_unique'
            );

            $table->index('parent_id');

            $table->index('thread_id');

            $table->index('edited_by');

            $table->index('removed_by');

            $table->index(
                ['group_id', 'is_pinned', 'removed_by'],
                'messages_group_id_is_pinned_removed_by_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};