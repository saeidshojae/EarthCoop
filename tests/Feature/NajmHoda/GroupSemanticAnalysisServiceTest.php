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

        $result = app(NajmHodaGroupSemanticAnalysisService::class)->analyze($group, now()->subMinute(), now()->addMinute(), 'summary');

        $this->assertFalse($result['available']);
        $this->assertNull($result['text']);
        $this->assertSame(1, $result['snapshot']['counts']['messages']);
    }

    public function test_reasoning_leakage_is_rejected_instead_of_being_shown_to_user(): void
    {
        config()->set('najm-hoda.provider.api_key', 'test-key');
        config()->set('najm-hoda.mock_mode', false);
        [$group] = $this->seedActivity();

        $guide = Mockery::mock(GuideAgent::class);
        $guide->shouldReceive('ask')->once()->andReturn("Here's a thinking process:\n1. Analyze User Request\nFinal check: all good.");
        $this->app->instance(GuideAgent::class, $guide);

        $result = app(NajmHodaGroupSemanticAnalysisService::class)->analyze($group, now()->subMinute(), now()->addMinute(), 'summary');

        $this->assertFalse($result['available']);
        $this->assertNull($result['text']);
    }

    public function test_valid_structured_semantic_output_is_rendered_with_claim_class_and_evidence(): void
    {
        config()->set('najm-hoda.provider.api_key', 'test-key');
        config()->set('najm-hoda.mock_mode', false);
        [$group, , $message] = $this->seedActivity();

        $guide = Mockery::mock(GuideAgent::class);
        $guide->shouldReceive('ask')->once()->andReturn(json_encode([
            'topics' => [[
                'title' => 'زمان جلسه بعدی',
                'claim_type' => 'interpretation',
                'insight' => 'پیشنهاد مطرح‌شده درباره برگزاری جلسه بعدی در شنبه است.',
                'sources' => ['message:' . $message->id],
                'evidence' => [[
                    'source' => 'message:' . $message->id,
                    'quote' => 'پیشنهاد می‌کنم جلسه بعدی شنبه برگزار شود.',
                ]],
            ]],
            'disagreements' => [],
            'followups' => [[
                'title' => 'تعیین زمان جلسه',
                'claim_type' => 'suggestion',
                'reason' => 'پیشنهاد مطرح شده ولی تصمیم ثبت‌شده‌ای وجود ندارد.',
                'sources' => ['message:' . $message->id],
                'evidence' => [[
                    'source' => 'message:' . $message->id,
                    'quote' => 'پیشنهاد می‌کنم جلسه بعدی شنبه برگزار شود.',
                ]],
            ]],
            'data_limits' => ['فقط یک پیام محتوایی در این بازه وجود دارد.'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->app->instance(GuideAgent::class, $guide);

        $result = app(NajmHodaGroupSemanticAnalysisService::class)->analyze($group, now()->subMinute(), now()->addMinute(), 'summary');

        $this->assertTrue($result['available']);
        $this->assertStringContainsString('[برداشت تحلیلی]', (string) $result['text']);
        $this->assertStringContainsString('زمان جلسه بعدی', (string) $result['text']);
        $this->assertStringContainsString('پیام #' . $message->id, (string) $result['text']);
        $this->assertStringContainsString('مورد ثبت‌شده‌ای وجود ندارد', (string) $result['text']);
        $this->assertStringNotContainsString('این تحلیل فقط بر داده‌های واقعی snapshot', (string) $result['text']);
    }

    public function test_forged_source_or_quote_is_not_rendered(): void
    {
        config()->set('najm-hoda.provider.api_key', 'test-key');
        config()->set('najm-hoda.mock_mode', false);
        [$group, , $message] = $this->seedActivity();

        $guide = Mockery::mock(GuideAgent::class);
        $guide->shouldReceive('ask')->once()->andReturn(json_encode([
            'topics' => [
                [
                    'title' => 'موضوع جعلی',
                    'claim_type' => 'fact',
                    'insight' => 'این موضوع نباید نمایش داده شود.',
                    'sources' => ['message:999999'],
                    'evidence' => [['source' => 'message:999999', 'quote' => 'متن جعلی']],
                ],
                [
                    'title' => 'quote جعلی',
                    'claim_type' => 'fact',
                    'insight' => 'این هم نباید نمایش داده شود.',
                    'sources' => ['message:' . $message->id],
                    'evidence' => [['source' => 'message:' . $message->id, 'quote' => 'ساعت ۹ شب']],
                ],
                [
                    'title' => 'پیشنهاد معتبر',
                    'claim_type' => 'interpretation',
                    'insight' => 'پیشنهاد شنبه در گفتگو مطرح شده است.',
                    'sources' => ['message:' . $message->id],
                    'evidence' => [['source' => 'message:' . $message->id, 'quote' => 'جلسه بعدی شنبه برگزار شود']],
                ],
            ],
            'disagreements' => [],
            'followups' => [],
            'data_limits' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->app->instance(GuideAgent::class, $guide);

        $result = app(NajmHodaGroupSemanticAnalysisService::class)->analyze($group, now()->subMinute(), now()->addMinute(), 'summary');

        $this->assertTrue($result['available']);
        $this->assertStringNotContainsString('موضوع جعلی', (string) $result['text']);
        $this->assertStringNotContainsString('quote جعلی', (string) $result['text']);
        $this->assertStringContainsString('پیشنهاد معتبر', (string) $result['text']);
    }

    public function test_fact_with_unsupported_hour_detail_is_demoted_to_interpretation(): void
    {
        config()->set('najm-hoda.provider.api_key', 'test-key');
        config()->set('najm-hoda.mock_mode', false);
        [$group, , $message] = $this->seedActivity();

        $guide = Mockery::mock(GuideAgent::class);
        $guide->shouldReceive('ask')->once()->andReturn(json_encode([
            'topics' => [[
                'title' => 'زمان جلسه',
                'claim_type' => 'fact',
                'insight' => 'جلسه بعدی شنبه ساعت هشت برگزار شود.',
                'sources' => ['message:' . $message->id],
                'evidence' => [[
                    'source' => 'message:' . $message->id,
                    'quote' => 'پیشنهاد می‌کنم جلسه بعدی شنبه برگزار شود.',
                ]],
            ]],
            'disagreements' => [],
            'followups' => [],
            'data_limits' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->app->instance(GuideAgent::class, $guide);

        $result = app(NajmHodaGroupSemanticAnalysisService::class)->analyze($group, now()->subMinute(), now()->addMinute(), 'summary');

        $this->assertTrue($result['available']);
        $this->assertSame('interpretation', $result['analysis']['topics'][0]['claim_type']);
        $this->assertStringContainsString('[برداشت تحلیلی]', (string) $result['text']);
        $this->assertStringNotContainsString('[واقعیت مستند]', (string) $result['text']);
    }

    /** @return array{0:Group,1:User,2:Message} */
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

        $message = Message::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'message' => 'پیشنهاد می‌کنم جلسه بعدی شنبه برگزار شود.',
        ]);

        return [$group, $user, $message];
    }
}
