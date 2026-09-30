<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'metadata')) {
                $table->json('metadata')->nullable()->after('phone');
            }
        });

        Schema::table('ticket_comments', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_comments', 'metadata')) {
                $table->json('metadata')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_comments', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });

        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
