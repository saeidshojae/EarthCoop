<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('founder_email_template_drafts', function (Blueprint $table): void {
            $table->unsignedBigInteger('template_id')->nullable()->change();
            $table->foreignId('communication_template_id')
                ->nullable()
                ->after('template_id')
                ->constrained('communication_templates')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('founder_email_template_drafts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('communication_template_id');
            $table->unsignedBigInteger('template_id')->nullable(false)->change();
        });
    }
};
