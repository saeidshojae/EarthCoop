<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reputation_rules', function (Blueprint $table) {
            $table->string('dimension', 32)->default('participation')->after('active');
            $table->boolean('convertible')->default(false)->after('dimension');
            $table->string('repeat_policy', 64)->nullable()->after('convertible');
        });

        Schema::table('user_point_transactions', function (Blueprint $table) {
            $table->string('dimension', 32)->default('participation')->after('action');
            $table->boolean('convertible')->default(false)->after('dimension');
        });
    }

    public function down(): void
    {
        Schema::table('user_point_transactions', function (Blueprint $table) {
            $table->dropColumn(['dimension', 'convertible']);
        });

        Schema::table('reputation_rules', function (Blueprint $table) {
            $table->dropColumn(['dimension', 'convertible', 'repeat_policy']);
        });
    }
};
