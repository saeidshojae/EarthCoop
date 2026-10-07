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

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
            $table->unique(['phone_country_code', 'phone'], 'users_phone_country_number_unique');
        });

        DB::table('users')
            ->where('edited', 1)
            ->whereNull('identity_edit_used_at')
            ->update(['identity_edit_used_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        $duplicateLocalPhones = DB::table('users')
            ->select('phone')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateLocalPhones) {
            throw new \RuntimeException(
                'Cannot restore globally unique local phone numbers while duplicates exist across country codes.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_country_number_unique');
            $table->unique('phone', 'users_phone_unique');
            $table->dropUnique('users_national_id_unique');
            $table->dropColumn(['nickname', 'phone_country_code', 'identity_edit_used_at']);
        });
    }
};
