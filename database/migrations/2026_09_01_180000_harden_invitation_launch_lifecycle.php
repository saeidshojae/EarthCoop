<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invitation_codes') && ! Schema::hasColumn('invitation_codes', 'completed_at')) {
            Schema::table('invitation_codes', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('used_at')->index();
            });
        }

        // Launch-safe defaults. Existing non-zero custom quota/expiry remain untouched.
        if (Schema::hasTable('setting')) {
            DB::table('setting')->where('id', 1)->update(['invation_status' => true]);
            DB::table('setting')->where('id', 1)
                ->where(function ($query) {
                    $query->whereNull('count_invation')->orWhere('count_invation', '<=', 0);
                })
                ->update(['count_invation' => 10]);
            DB::table('setting')->where('id', 1)
                ->where(function ($query) {
                    $query->whereNull('expire_invation_time')->orWhere('expire_invation_time', '<=', 0);
                })
                ->update(['expire_invation_time' => 72]);
        }

        // Migrate only the legacy default; deliberately preserve administrator custom weights.
        if (Schema::hasTable('reputation_rules')) {
            DB::table('reputation_rules')
                ->where('key', 'invite_member')
                ->where('weight', 10)
                ->update(['weight' => 100]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invitation_codes') && Schema::hasColumn('invitation_codes', 'completed_at')) {
            Schema::table('invitation_codes', function (Blueprint $table) {
                $table->dropIndex(['completed_at']);
                $table->dropColumn('completed_at');
            });
        }
    }
};
