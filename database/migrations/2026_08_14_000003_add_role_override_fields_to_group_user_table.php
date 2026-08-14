<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_user', function (Blueprint $table) {
            $table->boolean('role_override_active')->default(false)->after('role');
            $table->unsignedTinyInteger('role_override_original_role')->nullable()->after('role_override_active');
            $table->timestamp('role_override_started_at')->nullable()->after('role_override_original_role');
            $table->timestamp('role_override_expires_at')->nullable()->after('role_override_started_at');
            $table->unsignedBigInteger('role_override_changed_by')->nullable()->after('role_override_expires_at');
            $table->string('role_override_source', 32)->nullable()->after('role_override_changed_by');
            $table->index(['role_override_active', 'role_override_expires_at'], 'group_user_role_override_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::table('group_user', function (Blueprint $table) {
            $table->dropIndex('group_user_role_override_expiry_index');
            $table->dropColumn([
                'role_override_active',
                'role_override_original_role',
                'role_override_started_at',
                'role_override_expires_at',
                'role_override_changed_by',
                'role_override_source',
            ]);
        });
    }
};
