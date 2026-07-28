<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\NajmBahar\Models\Fee;

class NajmBaharFeeSeeder extends Seeder
{
    public function run()
    {
        // کارمزد عضویت سالانه
        Fee::updateOrCreate(
            ['name' => 'membership_fee'],
            [
                'name' => 'membership_fee',
                'type' => 'fixed',
                'fixed_amount' => 12,
                'percentage' => 0,
                'transaction_type' => 'fee',
                'min_amount' => null,
                'max_amount' => null,
                'is_active' => true,
                'description' => 'حق عضویت سالانه EarthCoop - 12 بهار'
            ]
        );

        // کارمزد تراکنش‌های فوری (مثال)
        Fee::updateOrCreate(
            ['name' => 'immediate_transaction_fee'],
            [
                'name' => 'immediate_transaction_fee',
                'type' => 'percentage',
                'fixed_amount' => 0,
                'percentage' => 0.5, // 0.5 درصد
                'transaction_type' => 'immediate',
                'min_amount' => 1,
                'max_amount' => 100,
                'is_active' => false, // غیرفعال به صورت پیش‌فرض
                'description' => 'کارمزد تراکنش‌های فوری - 0.5 درصد (حداقل 1، حداکثر 100 بهار)'
            ]
        );
    }
}