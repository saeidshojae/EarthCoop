<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invitation_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('invitation_codes', 'used_at')) {
                $table->timestamp('used_at')->nullable()->after('used');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitation_codes', function (Blueprint $table) {
            if (Schema::hasColumn('invitation_codes', 'used_at')) {
                $table->dropColumn('used_at');
            }
        });
    }
};


