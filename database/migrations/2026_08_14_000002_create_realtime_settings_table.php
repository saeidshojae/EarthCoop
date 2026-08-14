<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realtime_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('transport', 20)->default('polling');
            $table->string('provider', 20)->default('reverb');
            $table->boolean('fallback_to_polling')->default(true);
            $table->boolean('use_env_credentials')->default(true);
            $table->string('app_id')->nullable();
            $table->string('app_key')->nullable();
            $table->text('app_secret')->nullable();
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('scheme', 10)->default('https');
            $table->string('cluster', 30)->default('mt1');
            $table->unsignedInteger('polling_interval_ms')->default(1800);
            $table->string('last_test_status', 20)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realtime_settings');
    }
};
