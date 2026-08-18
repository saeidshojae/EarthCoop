<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupAttentionEvent;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupAttentionSweepCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('najm_hoda_group_attention_settings') || ! Schema::hasTable('najm_hoda_group_attention_events')) {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_08_17_044500_create_najm_hoda_group_attention_tables.php',
                '--force' => true,
            ]);
        }
    }

    public function test_sweep_evaluates_enabled_group_and_delivers_to_leadership(): void
    {
        Notification::fake();

        $group = Group::create(['name' => 'Attention sweep group', 'is_open' => 1]);
        $manager = User::create([
            'email' => uniqid('attention-sweep-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'مدیر',
            'last_name' => 'آزمایشی',
            'is_system' => false,
        ]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
        ]);

        NajmHodaGroupAttentionSetting::create([
            'group_id' => $group->id,
            'enabled' => true,
            'digest_mode' => 'immediate',
            'suppress_minutes' => 720,
            'timezone' => 'Asia/Tehran',
            'preferred_time' => '08:00',
        ]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'اقدام معوق آزمایشی',
            'status' => 'open',
            'priority' => 'urgent',
            'due_at' => now()->subHour(),
        ]);

        $exit = Artisan::call('najm-hoda:group-attention-sweep', ['--max-groups' => 20]);

        $this->assertSame(0, $exit);
        $this->assertGreaterThanOrEqual(1, NajmHodaGroupAttentionEvent::where('group_id', $group->id)->count());
        Notification::assertSentTo($manager, GenericNotification::class, fn (GenericNotification $notification) =>
            ($notification->context['source'] ?? null) === 'najm_hoda_proactive_attention'
            && str_contains($notification->message, 'اقدام معوق آزمایشی')
        );
    }
}
