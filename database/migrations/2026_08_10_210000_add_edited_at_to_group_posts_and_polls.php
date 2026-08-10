<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('updated_at');
        });
        Schema::table('polls', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('blogs', fn (Blueprint $table) => $table->dropColumn('edited_at'));
        Schema::table('polls', fn (Blueprint $table) => $table->dropColumn('edited_at'));
    }
};
