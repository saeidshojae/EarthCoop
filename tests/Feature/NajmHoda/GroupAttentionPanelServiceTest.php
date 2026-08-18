<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupAttentionEvent;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Services\NajmHoda\NajmHodaGroupAttentionPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupAttentionPanelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_snapshot_exposes_policy_active_events_and_operational_timestamps(): void
    {
        $group = $this->makeGroup('گروه تست پنل توجه');
        $item = NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'پیگیری گزارش جلسه',
            'status' => 'open',
            'priority' => 'urgent',
            'due_at' => now()->subHour(),
        ]);

        NajmHodaGroupAttentionSetting::create([
            'group_id' => $group->id,
            'enabled' => true,
            'digest_mode' => 'immediate',
            'preferred_time' => '08:00',
            'timezone' => 'Asia/Tehran',
            'due_soon_hours' => 48,
            'suppress_minutes' => 720,
            'last_evaluated_at' => now()->subMinute(),
            'last_digest_at' => now()->subMinutes(2),
        ]);

        foreach (['overdue', 'urgent', 'unassigned'] as $type) {
            NajmHodaGroupAttentionEvent::create([
                'group_id' => $group->id,
                'action_item_id' => $item->id,
                'event_type' => $type,
                'fingerprint' => hash('sha256', $group->id . ':' . $item->id . ':' . $type),
                'occurrences' => 2,
                'first_seen_at' => now()->subMinutes(10),
                'last_seen_at' => now(),
            ]);
        }

        $snapshot = app(NajmHodaGroupAttentionPanelService::class)->snapshot($group);

        $this->assertTrue($snapshot['policy']['enabled']);
        $this->assertSame('immediate', $snapshot['policy']['digest_mode']);
        $this->assertNotNull($snapshot['policy']['last_evaluated_at']);
        $this->assertNotNull($snapshot['policy']['last_digest_at']);
        $this->assertSame(3, $snapshot['stats']['active_events']);
        $this->assertSame(1, $snapshot['stats']['overdue']);
        $this->assertSame(1, $snapshot['stats']['urgent']);
        $this->assertSame(1, $snapshot['stats']['unassigned']);
        $this->assertCount(3, $snapshot['events']);
        $this->assertSame('پیگیری گزارش جلسه', $snapshot['events'][0]['action_item']['title']);
    }

    public function test_panel_policy_update_only_changes_attention_policy_fields(): void
    {
        $group = $this->makeGroup('گروه تست تنظیم policy');
        $service = app(NajmHodaGroupAttentionPanelService::class);

        $setting = $service->updatePolicy($group, [
            'enabled' => true,
            'digest_mode' => 'daily',
            'preferred_time' => '09:30',
            'due_soon_hours' => 24,
            'suppress_minutes' => 360,
            'alert_unassigned' => false,
            'group_id' => 999999,
            'last_digest_at' => now(),
        ]);

        $this->assertSame($group->id, (int) $setting->group_id);
        $this->assertTrue($setting->enabled);
        $this->assertSame('daily', $setting->digest_mode);
        $this->assertSame('09:30', $setting->preferred_time);
        $this->assertSame(24, (int) $setting->due_soon_hours);
        $this->assertSame(360, (int) $setting->suppress_minutes);
        $this->assertFalse($setting->alert_unassigned);
        $this->assertNull($setting->last_digest_at);
    }

    private function makeGroup(string $name): Group
    {
        return Group::query()->create([
            'group_type' => 0,
            'name' => $name,
            'location_level' => 10,
            'is_open' => 1,
        ]);
    }
}
