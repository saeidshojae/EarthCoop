<?php

namespace Tests\Feature\Admin;

use App\Models\RealtimeSetting;
use App\Models\User;
use App\Services\GroupChat\RealtimeSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RealtimeSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_save_runtime_polling_settings_without_a_frontend_rebuild(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.system-settings.realtime.update'), [
            'enabled' => '1',
            'transport' => 'polling',
            'provider' => 'reverb',
            'fallback_to_polling' => '1',
            'use_env_credentials' => '1',
            'scheme' => 'https',
            'port' => 443,
            'cluster' => 'mt1',
            'polling_interval_ms' => 1400,
        ])->assertRedirect(route('admin.system-settings.realtime.index'));

        $this->assertDatabaseHas('realtime_settings', [
            'transport' => 'polling',
            'polling_interval_ms' => 1400,
            'updated_by' => $admin->id,
        ]);

        $effective = app(RealtimeSettingsService::class)->effective();
        $this->assertSame('polling', $effective['transport']);
        $this->assertSame(1400, $effective['polling_interval_ms']);
    }

    public function test_database_secret_is_encrypted_and_never_exposed_in_public_config_or_page(): void
    {
        $admin = $this->makeAdmin();
        $secret = 'private-secret-' . uniqid();

        $this->actingAs($admin)->put(route('admin.system-settings.realtime.update'), [
            'enabled' => '1',
            'transport' => 'auto',
            'provider' => 'soketi',
            'fallback_to_polling' => '1',
            'app_id' => 'earthcoop-app',
            'app_key' => 'earthcoop-public-key',
            'app_secret' => $secret,
            'host' => 'ws.earthcoop.test',
            'port' => 443,
            'scheme' => 'https',
            'cluster' => 'mt1',
            'polling_interval_ms' => 1800,
        ])->assertRedirect(route('admin.system-settings.realtime.index'));

        $setting = RealtimeSetting::query()->firstOrFail();
        $stored = DB::table('realtime_settings')->where('id', $setting->id)->value('app_secret');
        $this->assertNotSame($secret, $stored);
        $this->assertSame($secret, $setting->app_secret);

        $public = app(RealtimeSettingsService::class)->publicConfig();
        $this->assertSame('earthcoop-public-key', $public['key']);
        $this->assertArrayNotHasKey('app_secret', $public);
        $this->assertArrayNotHasKey('secret', $public);

        $this->actingAs($admin)->get(route('admin.system-settings.realtime.index'))
            ->assertOk()
            ->assertDontSee($secret);
    }

    public function test_polling_connection_test_does_not_attempt_an_external_websocket_call(): void
    {
        $admin = $this->makeAdmin();
        $setting = RealtimeSetting::create([
            'enabled' => true,
            'transport' => 'polling',
            'provider' => 'reverb',
            'use_env_credentials' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.system-settings.realtime.test'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('realtime_settings', [
            'id' => $setting->id,
            'last_test_status' => 'success',
        ]);
    }

    public function test_an_ordinary_user_cannot_manage_realtime_credentials(): void
    {
        $user = $this->makeAdmin();
        $user->forceFill(['is_admin' => false])->save();

        $this->actingAs($user)->get(route('admin.system-settings.realtime.index'))
            ->assertRedirect('/home');
    }

    public function test_super_admin_dashboard_exposes_working_system_and_realtime_settings_links(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.system-settings.index'), false)
            ->assertSee(route('admin.system-settings.realtime.index'), false);

        $this->actingAs($admin)->get(route('admin.dashboard', ['general' => 1]))
            ->assertOk()
            ->assertSee(route('admin.categories.index'), false);
    }

    private function makeAdmin(): User
    {
        $admin = User::create([
            'first_name' => 'Realtime',
            'last_name' => 'Admin',
            'email' => 'realtime-admin-' . Str::uuid() . '@example.test',
            'password' => bcrypt('password123'),
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }
}
