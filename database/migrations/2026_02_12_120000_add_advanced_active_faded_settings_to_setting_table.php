<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            // نوع تخصیص اکتیو: percentage یا fixed_amount
            if (!Schema::hasColumn('setting', 'najm_bahar_initial_active_type')) {
                $table->string('najm_bahar_initial_active_type', 20)
                    ->default('percentage')
                    ->after('najm_bahar_initial_active_percentage')
                    ->comment('نوع تخصیص: percentage = درصدی, fixed_amount = مبلغ ثابت');
            }
            
            // مبلغ ثابت اکتیو (در صورت انتخاب fixed_amount)
            if (!Schema::hasColumn('setting', 'najm_bahar_initial_active_fixed_amount')) {
                $table->bigInteger('najm_bahar_initial_active_fixed_amount')
                    ->default(0)
                    ->after('najm_bahar_initial_active_type')
                    ->comment('مبلغ ثابت اکتیو به واحد گل');
            }
            
            // فعال‌سازی خودکار دوره‌ای
            if (!Schema::hasColumn('setting', 'najm_bahar_auto_activation_enabled')) {
                $table->boolean('najm_bahar_auto_activation_enabled')
                    ->default(false)
                    ->after('najm_bahar_initial_active_fixed_amount')
                    ->comment('فعال‌سازی خودکار موجودی کمرنگ');
            }
            
            // دوره فعال‌سازی: monthly, yearly
            if (!Schema::hasColumn('setting', 'najm_bahar_auto_activation_period')) {
                $table->string('najm_bahar_auto_activation_period', 20)
                    ->default('monthly')
                    ->after('najm_bahar_auto_activation_enabled')
                    ->comment('دوره فعال‌سازی: monthly = ماهانه, yearly = سالانه');
            }
            
            // مقدار فعال‌سازی در هر دوره
            if (!Schema::hasColumn('setting', 'najm_bahar_auto_activation_amount')) {
                $table->bigInteger('najm_bahar_auto_activation_amount')
                    ->default(0)
                    ->after('najm_bahar_auto_activation_period')
                    ->comment('مقدار فعال‌سازی در هر دوره (به گل)');
            }
        });

        // مقداردهی اولیه
        DB::table('setting')->update([
            'najm_bahar_initial_active_type' => 'percentage',
            'najm_bahar_auto_activation_enabled' => false,
            'najm_bahar_auto_activation_period' => 'monthly',
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('setting')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            $columns = [
                'najm_bahar_initial_active_type',
                'najm_bahar_initial_active_fixed_amount',
                'najm_bahar_auto_activation_enabled',
                'najm_bahar_auto_activation_period',
                'najm_bahar_auto_activation_amount',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('setting', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
