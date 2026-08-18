<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_bahar_monetary_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->json('parameters');
            $table->text('reason')->nullable();
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_until')->nullable()->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_from', 'effective_until'], 'nb_policy_active_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_monetary_policy_versions');
    }
};
