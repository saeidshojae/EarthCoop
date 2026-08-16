<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\NajmHoda\Agents\GuideAgent;
use App\Services\NajmHoda\NajmHodaGroupSemanticAnalysisService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class GroupSemanticAnalysisServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_semantic_layer_keeps_grounded_snapshot_when_provider_is_unavailable(): void
    {
        config()->set('najm-hoda.provider.api_key', null);
        config()->set('najm-hoda.mock_mode', false);

        [$group] = $this->seedActivity();

        $result = app(NajmHodaGroupSemanticAnalysisService::class)->analyze(
            $group,
            now()->subMinute(),
            now()->addMinute(),
            'summary'
        );

        $this->assertFalse($result['available']);
        $this->assertNull($result['text']);
        $this->assertSame(1, $result['snapshot']['counts']['messages']);
        $this->assertSame('پیشنهاد می‌کنم جلسه بعدی شنبه برگزار شود.', $result['snapshot']['messages'][0]['text']);
    }

    public function test_reasoning_leakage_is_rejected_instead_of_being_shown_to_user(): void
    {
        config()->set('najm-hoda.provider.api_key', 'test-key');
        config()->set('najm-hoda.mock_mode', false);

        [$group] = $this->seedActivity();

        $guide = Mockery::mock(GuideAgent::class);
        $guide->shouldReceive('ask')->once()->andReturn(
            "Here's a thinking process:\n1. Analyze User Request\n2. Examine SOURCE_JSON\nFinal check: all good.\n\nخلاصه نهایی کاربر"
        );
        $this->app->instance(GuideAgent::class, $guide);

        $result = app(NajmHodaGroupSemanticAnalysisService::class)->analyze(
            $group,
            now()->subMinute(),
            now()->addMinute(),
            'summary'
        );

        $this->assertFalse($result['available']);
        $this->assertNull($result['text']);
        $this->assertSame(1, $result['snapshot']['counts']['messages']);
    }

    public function test_only_explicit_final_envelope_is_returned(): void
    {
        config()->set('najm-hoda.provider.api_key', 'test-key');
        config()->set('najm-hoda.mock_mode', false);

        [$group] = $this->seedActivity();

        $guide = Mockery::mock(GuideAgent::class);
        $guide->shouldReceive('ask')->once()->andReturn(
            "<final>موضوع اصلی گفت‌وگو، پیشنهاد برگزاری جلسه در شنبه است (پیام #1). هنوز تصمیم قطعی ثبت نشده است.</final>"
        );
        $this->app->instance(GuideAgent::class, $guide);

        $result = app(NajmHodaGroupSemanticAnalysisService::class)->analyze(
            $group,
            now()->subMinute(),
            now()->addMinute(),
            'summary'
        );

        $this->assertTrue($result['available']);
        $this->assertSame(
            'موضوع اصلی گفت‌وگو، پیشنهاد برگزاری جلسه در شنبه است (پیام #1). هنوز تصمیم قطعی ثبت نشده است.',
            $result['text']
        );
        $this->assertStringNotContainsString('<final>', (string) $result['text']);
        $this->assertStringNotContainsString('thinking process', mb_strtolower((string) $result['text']));
    }

    /** @return array{0:Group,1:User} */
    private function seedActivity(): array
    {
        $group = Group::create(['name' => 'Semantic group', 'is_open' => 1]);
        $user = User::create([
            'email' => uniqid('semantic-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'عضو',
            'last_name' => 'گروه',
            'is_system' => false,
        ]);

        Message::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'message' => 'پیشنهاد می‌کنم جلسه بعدی شنبه برگزار شود.',
        ]);

        return [$group, $user];
    }
}
