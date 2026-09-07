<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_offices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('office_type', 32);
            $table->string('scope_type', 64)->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('status', 24)->default('active');
            $table->json('numbering_policy')->nullable();
            $table->string('default_confidentiality', 32)->default('office_members');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id'], 'secretariat_offices_scope_idx');
            $table->index(['office_type', 'status'], 'secretariat_offices_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_offices');
    }
};
