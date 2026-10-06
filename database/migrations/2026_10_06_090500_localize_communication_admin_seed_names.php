<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $names = [
            'onboarding.welcome' => 'خوش‌آمدگویی پس از ثبت‌نام',
            'reports.member.weekly' => 'گزارش هفتگی اعضا',
            'reports.manager.weekly' => 'گزارش هفتگی مدیران',
            'reports.inspector.weekly' => 'گزارش هفتگی بازرسان',
        ];

        foreach ($names as $key => $name) {
            DB::table('communication_templates')
                ->where('key', $key)
                ->update([
                    'name' => $name,
                    'updated_at' => $now,
                ]);

            DB::table('communication_rules')
                ->where('key', $key.'.rule')
                ->update([
                    'name' => $name,
                    'updated_at' => $now,
                ]);
        }

        DB::table('communication_sender_identities')
            ->where('key', 'onboarding')
            ->update([
                'display_name' => 'EarthCoop',
                'purpose' => 'ارتباطات خوش‌آمدگویی و شروع عضویت',
                'updated_at' => $now,
            ]);

        DB::table('communication_sender_identities')
            ->where('key', 'reports')
            ->update([
                'display_name' => 'گزارش‌های EarthCoop',
                'purpose' => 'گزارش‌های عملیاتی متناسب با نقش',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $now = now();

        $names = [
            'onboarding.welcome' => 'Welcome',
            'reports.member.weekly' => 'Weekly member report',
            'reports.manager.weekly' => 'Weekly manager report',
            'reports.inspector.weekly' => 'Weekly inspector report',
        ];

        foreach ($names as $key => $name) {
            DB::table('communication_templates')
                ->where('key', $key)
                ->update([
                    'name' => $name,
                    'updated_at' => $now,
                ]);

            DB::table('communication_rules')
                ->where('key', $key.'.rule')
                ->update([
                    'name' => $key === 'onboarding.welcome' ? 'Welcome after registration' : $name,
                    'updated_at' => $now,
                ]);
        }

        DB::table('communication_sender_identities')
            ->where('key', 'onboarding')
            ->update([
                'display_name' => 'EarthCoop',
                'purpose' => 'Member onboarding communications',
                'updated_at' => $now,
            ]);

        DB::table('communication_sender_identities')
            ->where('key', 'reports')
            ->update([
                'display_name' => 'EarthCoop',
                'purpose' => 'Role-aware operational reports',
                'updated_at' => $now,
            ]);
    }
};
