<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // اضافه کردن سیدر LocationSeeder
        $this->call(LocationsSeeder::class);

        // اضافه کردن سیدر StockSeeder
        $this->call(StockSeeder::class);

        // می‌توانید سایر سیدرها را نیز اینجا اضافه کنید
        // \App\Models\User::factory(10)->create();
        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        // NajmBahar system accounts
        $this->call(\Database\Seeders\NajmBaharSeeder::class);
    }
}
