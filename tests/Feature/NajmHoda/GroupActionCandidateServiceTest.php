<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\NajmHoda\Agents\GuideAgent;
use App\Services\NajmHoda\NajmHodaGroupActionCandidateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class GroupActionCandidateServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_candidate_requires_exact_evidence_from_real_source(): void
    {
        config()->set('najm-hoda.provider.api_key', 'test-key');
        config()->set('najm-hoda.mock_mode', false);

        $group = Group::create(['name' => 'Action candidate group', 'is_open' => 1]);
        $user = User::create([
            'email' => uniqid('action-candidate-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'عضو',
            'last_name' => 'گروه',
            'is_system' => false,
        ]);
        $message = Message::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'message' => 'لطفاً گزارش جلسه را تا فردا آماده کنید.',
        ]);

        $guide = Mockery::mock(GuideAgent::class);
        $guide->shouldReceive('ask')->once()->andReturn(json_encode([
            'action_candidates' => [
                [
                    'title' => 'آماده‌سازی گزارش جلسه',
                    'details' => 'گزارش جلسه تهیه شود.',
                    'assignee_name' => null,
                    'due_text' => 'فردا',
                    'priority' => 'high',
                    'source' => 'message:' . $message->id,
                    'evidence' => 'لطفاً گزارش جلسه را تا فردا آماده کنید.',
                ],
                [
                    'title' => 'مورد جعلی',
                    'details' => 'نباید پذیرفته شود.',
                    'assignee_name' => null,
                    'due_text' => null,
                    'priority' => 'medium',
                    'source' => 'message:' . $message->id,
                    'evidence' => 'این متن در پیام وجود ندارد',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->app->instance(GuideAgent::class, $guide);

        $result = app(NajmHodaGroupActionCandidateService::class)->extract(
            $group,
            now()->subMinute(),
            now()->addMinute()
        );

        $this->assertTrue($result['available']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('آماده‌سازی گزارش جلسه', $result['candidates'][0]['title']);
        $this->assertSame('فردا', $result['candidates'][0]['due_text']);
        $this->assertSame('message:' . $message->id, $result['candidates'][0]['source']);
    }

    public function test_provider_unavailable_never_invents_candidates(): void
    {
        config()->set('najm-hoda.provider.api_key', null);
        config()->set('najm-hoda.mock_mode', false);

        $group = Group::create(['name' => 'Unavailable action group', 'is_open' => 1]);
        $result = app(NajmHodaGroupActionCandidateService::class)->extract(
            $group,
            now()->subMinute(),
            now()->addMinute()
        );

        $this->assertFalse($result['available']);
        $this->assertSame([], $result['candidates']);
    }
}
