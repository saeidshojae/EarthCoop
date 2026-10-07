<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateNationalIds = DB::table('users')
            ->select('national_id')
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->groupBy('national_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateNationalIds) {
            throw new \RuntimeException('Cannot enforce unique national_id while duplicate values exist.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 80)->nullable()->after('last_name');
            $table->string('phone_country_code', 16)->default('+98')->after('phone');
            $table->timestamp('identity_edit_used_at')->nullable()->after('edited');
            $table->unique('national_id', 'users_national_id_unique');
        });

        DB::table('users')
            ->where('edited', 1)
            ->whereNull('identity_edit_used_at')
            ->update(['identity_edit_used_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_national_id_unique');
            $table->dropColumn(['nickname', 'phone_country_code', 'identity_edit_used_at']);
        });
    }
};
