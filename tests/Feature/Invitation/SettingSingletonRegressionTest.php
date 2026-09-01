<?php

namespace Tests\Feature\Invitation;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingSingletonRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_seeder_restores_id_one_even_after_auto_increment_has_advanced(): void
    {
        DB::table('setting')->delete();

        $sentinel = new Setting();
        $sentinel->forceFill(['id' => 50]);
        $sentinel->save();
        $sentinel->delete();

        app(SettingSeeder::class)->run();

        $setting = Setting::query()->findOrFail(1);

        $this->assertSame(1, (int) $setting->id);
        $this->assertTrue((bool) $setting->invation_status);
        $this->assertSame(10, (int) $setting->count_invation);
        $this->assertSame(72, (int) $setting->expire_invation_time);
        $this->assertSame(1, Setting::query()->count());
    }
}
