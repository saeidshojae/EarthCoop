<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    public function up()
    {
        Schema::table('setting', function (Blueprint $table) {
            $table->integer('reputation_to_gol_ratio')->default(100)->after('najm_bahar_auto_activation_amount')
                ->comment('نسبت تبدیل امتیاز به گل (مثال: 100 امتیاز = 1 گل)');
            $table->boolean('reputation_conversion_enabled')->default(true)->after('reputation_to_gol_ratio')
                ->comment('فعال/غیرفعال بودن تبدیل امتیاز به پول اکتیو');
        });

        // تنظیم مقدار پیش‌فرض برای رکورد موجود
        $setting = Setting::first();
        if ($setting) {
            $setting->update([
                'reputation_to_gol_ratio' => 100,
                'reputation_conversion_enabled' => true,
            ]);
        }
    }

    public function down()
    {
        Schema::table('setting', function (Blueprint $table) {
            $table->dropColumn(['reputation_to_gol_ratio', 'reputation_conversion_enabled']);
        });
    }
};
