<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reported_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->foreignId('group_id')->nullable()->constrained('groups')->onDelete('cascade');
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->text('manager_note')->nullable();
            $table->foreignId('reviewed_by_manager')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('escalated_to_admin')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('reported_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reported_messages');
    }
};