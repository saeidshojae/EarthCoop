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

        // Legacy referral points were awarded from Najm Bahar with the same
        // invitation reference. Mark those invitations as already successful so
        // the new successful-invite quota starts from historical truth and no
        // rewarded referral can become available for a second success lifecycle.
        if (Schema::hasTable('invitation_codes') && Schema::hasTable('user_point_transactions')) {
            $rewardedInvitationIds = DB::table('user_point_transactions')
                ->where('action', 'invite_member')
                ->whereNotNull('reference_id')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values();

            if ($rewardedInvitationIds->isNotEmpty()) {
                DB::table('invitation_codes')
                    ->whereIn('id', $rewardedInvitationIds->all())
                    ->whereNull('completed_at')
                    ->update([
                        'completed_at' => DB::raw('COALESCE(used_at, updated_at, created_at)'),
                    ]);
            }
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
