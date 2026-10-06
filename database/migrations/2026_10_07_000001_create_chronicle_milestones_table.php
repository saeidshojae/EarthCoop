<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chronicle_milestones', function (Blueprint $table) {
            $table->id();
            $table->date('occurred_on')->index();
            $table->json('title_translations');
            $table->json('description_translations')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['is_published', 'occurred_on', 'sort_order'],
                'chronicle_milestones_public_order_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chronicle_milestones');
    }
};
