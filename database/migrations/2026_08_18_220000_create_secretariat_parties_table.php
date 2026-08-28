<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('secretariat_records')->cascadeOnDelete();
            $table->string('role', 32);
            $table->string('party_type', 24);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('display_name', 255);
            $table->string('organization_name', 255)->nullable();
            $table->string('email', 320)->nullable();
            $table->string('phone', 64)->nullable();
            $table->text('address')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['record_id', 'role'], 'secretariat_parties_record_role_idx');
            $table->index(['user_id', 'role'], 'secretariat_parties_user_role_idx');
            $table->index(['group_id', 'role'], 'secretariat_parties_group_role_idx');
            $table->index('display_name', 'secretariat_parties_display_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_parties');
    }
};
