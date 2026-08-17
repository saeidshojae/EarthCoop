<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupAttentionEvent;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Services\NajmHoda\NajmHodaGroupAttentionEvaluatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GroupAttentionEvaluatorServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_disabled_group_produces_no_attention_events(): void
    {
        $group = Group::create(['name' => 'Disabled proactive group', 'is_open' => 1]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'Overdue but disabled',
            'status' => 'open',
            'priority' => 'urgent',
            'due_at' => now()->subHour(),
        ]);

        $result = app(NajmHodaGroupAttentionEvaluatorService::class)->evaluateGroup($group);

        $this->assertSame(0, $result['events']);
        $this->assertSame(0, NajmHodaGroupAttentionEvent::where('group_id', $group->id)->count());
    }

    public function test_evaluator_deduplicates_repeated_risk_and_resolves_when_condition_clears(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 10:00:00'));
        $group = Group::create(['name' => 'Proactive attention group', 'is_open' => 1]);
        NajmHodaGroupAttentionSetting::create([
            'group_id' => $group->id,
            'enabled' => true,
            'due_soon_hours' => 48,
            'suppress_minutes' => 720,
            'timezone' => 'Asia/Tehran',
            'preferred_time' => '08:00',
        ]);

        $item = NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'پیگیری فوری مجوز',
            'status' => 'open',
            'priority' => 'urgent',
            'due_at' => now()->subHour(),
        ]);

        $service = app(NajmHodaGroupAttentionEvaluatorService::class);
        $first = $service->evaluateGroup($group);
        $this->assertSame(3, $first['events']); // overdue + urgent + unassigned
        $this->assertSame(3, NajmHodaGroupAttentionEvent::where('group_id', $group->id)->count());

        Carbon::setTestNow(now()->addMinutes(15));
        $service->evaluateGroup($group);
        $this->assertSame(3, NajmHodaGroupAttentionEvent::where('group_id', $group->id)->count(), 'Repeated evaluation must not duplicate the same risk event.');
        $this->assertSame(2, (int) NajmHodaGroupAttentionEvent::where('group_id', $group->id)->first()->occurrences);

        $pending = $service->pendingNotifications($group);
        $this->assertCount(3, $pending);
        $pending->each(function ($event) {
            $event->forceFill(['last_notified_at' => now()])->save();
        });
        $this->assertCount(0, $service->pendingNotifications($group), 'Suppression window must prevent immediate repeat notification.');

        $item->forceFill([
            'status' => 'done',
            'priority' => 'medium',
            'assigned_user_id' => null,
            'assignee_name' => 'مسئول انجام',
        ])->save();
        Carbon::setTestNow(now()->addMinutes(15));
        $third = $service->evaluateGroup($group);

        $this->assertSame(3, $third['resolved']);
        $this->assertSame(0, NajmHodaGroupAttentionEvent::where('group_id', $group->id)->whereNull('resolved_at')->count());
    }
}
