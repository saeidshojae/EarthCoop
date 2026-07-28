<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User; // فرض بر وجود مدل User
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Wallet;
use App\Modules\Stock\Models\Holding;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // ایجاد کاربر نمونه اگر وجود ندارد
        $user = User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => bcrypt('password'),
        ]);

        // ایجاد سهام نمونه
        $stock = Stock::create([
            'startup_valuation' => 1000000.00, // ارزش پایه استارتاپ
            'total_shares' => 10000,           // تعداد کل سهام
            'base_share_price' => 100.00,      // ارزش پایه هر سهم
            'info' => 'سهام نمونه شرکت New Earth Coop',
        ]);

        // ایجاد حراج نمونه
        Auction::create([
            'stock_id' => $stock->id,
            'shares_count' => 100,
            'base_price' => 100.00,
            'start_time' => now(),
            'end_time' => now()->addDays(7),
            'status' => 'active',
            'info' => 'حراج نمونه برای سهام',
        ]);

        // ایجاد کیف پول نمونه برای کاربر (اگر وجود دارد ایجاد نکند)
        Wallet::firstOrCreate([
            'user_id' => $user->id,
            'currency' => 'IRR',
        ], [
            'balance' => 5000.00,
        ]);

        // ایجاد نگهداری نمونه
        Holding::create([
            'user_id' => $user->id,
            'stock_id' => $stock->id,
            'quantity' => 50,
        ]);

        // می‌توانید داده‌های بیشتری اضافه کنید
    }
}