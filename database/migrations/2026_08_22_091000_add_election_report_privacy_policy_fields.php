<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_setting', function (Blueprint $table) {
            $table->unsignedInteger('election_report_min_distinct_voters')->default(10);
            $table->unsignedInteger('election_report_bucket_days')->default(7);
            $table->unsignedInteger('election_meaningful_trend_min_net_change')->default(3);
        });

        Schema::table('election_policy_versions', function (Blueprint $table) {
            $table->unsignedInteger('report_min_distinct_voters')->default(10);
            $table->unsignedInteger('report_bucket_days')->default(7);
            $table->unsignedInteger('meaningful_trend_min_net_change')->default(3);
        });
    }

    public function down(): void
    {
        Schema::table('election_policy_versions', function (Blueprint $table) {
            $table->dropColumn([
                'report_min_distinct_voters',
                'report_bucket_days',
                'meaningful_trend_min_net_change',
            ]);
        });

        Schema::table('group_setting', function (Blueprint $table) {
            $table->dropColumn([
                'election_report_min_distinct_voters',
                'election_report_bucket_days',
                'election_meaningful_trend_min_net_change',
            ]);
        });
    }
};
