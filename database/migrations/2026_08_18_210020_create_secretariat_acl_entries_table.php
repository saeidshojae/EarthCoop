<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_acl_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('secretariat_records')->restrictOnDelete();
            $table->string('principal_type', 32);
            $table->unsignedBigInteger('principal_id');
            $table->string('permission', 32)->default('view');
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['record_id', 'principal_type', 'principal_id', 'permission', 'revoked_at'],
                'secretariat_acl_principal_permission_idx'
            );
            $table->index(
                ['principal_type', 'principal_id', 'permission', 'revoked_at', 'expires_at'],
                'secretariat_acl_lookup_idx'
            );
            $table->index(['record_id', 'permission', 'revoked_at'], 'secretariat_acl_record_permission_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_acl_entries');
    }
};
