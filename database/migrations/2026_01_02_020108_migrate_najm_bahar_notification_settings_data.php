<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // انتقال داده‌های NajmBaharNotificationSetting به NotificationSetting
        if (Schema::hasTable('najm_bahar_notification_settings') && Schema::hasTable('notification_settings')) {
            $oldSettings = DB::table('najm_bahar_notification_settings')->get();
            
            foreach ($oldSettings as $oldSetting) {
                // بررسی وجود تنظیمات در NotificationSetting
                $existing = DB::table('notification_settings')
                    ->where('user_id', $oldSetting->user_id)
                    ->first();
                
                if ($existing) {
                    // به‌روزرسانی تنظیمات موجود
                    DB::table('notification_settings')
                        ->where('user_id', $oldSetting->user_id)
                        ->update([
                            'najm_bahar_transaction' => $oldSetting->transaction_notifications ?? true,
                            'najm_bahar_low_balance' => $oldSetting->low_balance_notifications ?? true,
                            'najm_bahar_large_transaction' => $oldSetting->large_transaction_notifications ?? true,
                            'najm_bahar_scheduled_transaction' => $oldSetting->scheduled_transaction_notifications ?? true,
                            'najm_bahar_low_balance_threshold' => $oldSetting->low_balance_threshold ?? 1000,
                            'najm_bahar_large_transaction_threshold' => $oldSetting->large_transaction_threshold ?? 100000,
                            'updated_at' => now(),
                        ]);
                } else {
                    // ایجاد تنظیمات جدید
                    $defaults = \App\Models\NotificationSetting::getDefaults();
                    DB::table('notification_settings')->insert([
                        'user_id' => $oldSetting->user_id,
                        'najm_bahar_transaction' => $oldSetting->transaction_notifications ?? true,
                        'najm_bahar_low_balance' => $oldSetting->low_balance_notifications ?? true,
                        'najm_bahar_large_transaction' => $oldSetting->large_transaction_notifications ?? true,
                        'najm_bahar_scheduled_transaction' => $oldSetting->scheduled_transaction_notifications ?? true,
                        'najm_bahar_low_balance_threshold' => $oldSetting->low_balance_threshold ?? 1000,
                        'najm_bahar_large_transaction_threshold' => $oldSetting->large_transaction_threshold ?? 100000,
                        ...$defaults,
                        'created_at' => $oldSetting->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            // حذف جدول قدیمی (اختیاری - می‌توانید بعداً انجام دهید)
            // Schema::dropIfExists('najm_bahar_notification_settings');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // نمی‌توانیم داده‌ها را به حالت قبل برگردانیم
        // چون ممکن است تغییراتی در NotificationSetting ایجاد شده باشد
    }
};
