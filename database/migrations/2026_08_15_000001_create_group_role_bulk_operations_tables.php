<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_role_bulk_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->json('filters');
            $table->unsignedTinyInteger('source_role');
            $table->unsignedTinyInteger('target_role');
            $table->string('duration_unit', 16);
            $table->unsignedSmallInteger('duration_value')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->unsignedInteger('applied_items')->default(0);
            $table->unsignedInteger('cancelled_items')->default(0);
            $table->unsignedInteger('skipped_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('group_role_bulk_operation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained('group_role_bulk_operations')->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('group_user')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('result', 20)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['operation_id', 'membership_id'], 'group_role_bulk_item_unique');
            $table->index(['operation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_role_bulk_operation_items');
        Schema::dropIfExists('group_role_bulk_operations');
    }
};
