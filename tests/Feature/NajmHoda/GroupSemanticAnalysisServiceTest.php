<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaGroupSemanticAnalysisService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GroupSemanticAnalysisServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_semantic_layer_keeps_grounded_snapshot_when_provider_is_unavailable(): void
    {
        config()->set('najm-hoda.provider.api_key', null);
        config()->set('najm-hoda.mock_mode', false);

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
}
