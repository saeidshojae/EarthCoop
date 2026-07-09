<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            if (!Schema::hasColumn('setting', 'reputation_to_gol_ratio')) {
                $table->integer('reputation_to_gol_ratio')->default(100)->after('najm_bahar_auto_activation_amount')
                    ->comment('نسبت تبدیل امتیاز به گل (مثال: 100 امتیاز = 1 گل)');
            }
            if (!Schema::hasColumn('setting', 'reputation_conversion_enabled')) {
                $table->boolean('reputation_conversion_enabled')->default(true)->after('reputation_to_gol_ratio')
                    ->comment('فعال/غیرفعال بودن تبدیل امتیاز به پول اکتیو');
            }
        });

        // تنظیم مقدار پیش‌فرض برای رکورد موجود
        $setting = DB::table('setting')->first();
        if ($setting) {
            DB::table('setting')->update([
                'reputation_to_gol_ratio' => 100,
                'reputation_conversion_enabled' => true,
            ]);
        }
    }

    public function down()
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            $toDrop = [];
            if (Schema::hasColumn('setting', 'reputation_to_gol_ratio')) {
                $toDrop[] = 'reputation_to_gol_ratio';
            }
            if (Schema::hasColumn('setting', 'reputation_conversion_enabled')) {
                $toDrop[] = 'reputation_conversion_enabled';
            }

            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
