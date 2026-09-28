<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('native_devices', function (Blueprint $table) {
            $table->string('push_provider', 20)->nullable()->after('push_capable');
            $table->text('push_token')->nullable()->after('push_provider');
            $table->string('push_token_hash', 64)->nullable()->after('push_token');
            $table->timestamp('push_token_updated_at')->nullable()->after('push_token_hash');
            $table->timestamp('push_enabled_at')->nullable()->after('push_token_updated_at');
            $table->timestamp('push_disabled_at')->nullable()->after('push_enabled_at');
            $table->timestamp('last_push_success_at')->nullable()->after('push_disabled_at');
            $table->timestamp('last_push_failure_at')->nullable()->after('last_push_success_at');
            $table->string('last_push_failure_code', 100)->nullable()->after('last_push_failure_at');
            $table->unique(['push_provider', 'push_token_hash'], 'native_devices_push_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('native_devices', function (Blueprint $table) {
            $table->dropUnique('native_devices_push_token_unique');
            $table->dropColumn([
                'push_provider',
                'push_token',
                'push_token_hash',
                'push_token_updated_at',
                'push_enabled_at',
                'push_disabled_at',
                'last_push_success_at',
                'last_push_failure_at',
                'last_push_failure_code',
            ]);
        });
    }
};
