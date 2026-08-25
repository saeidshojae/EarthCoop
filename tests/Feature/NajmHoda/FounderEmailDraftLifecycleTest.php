<?php

namespace Tests\Feature\NajmHoda;

use App\Models\FounderEmailDraft;
use App\Models\User;
use App\Services\Email\EmailDeliveryService;
use App\Services\NajmHoda\FounderOps\FounderDraftEditingService;
use App\Services\NajmHoda\FounderOps\FounderEmailDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class FounderEmailDraftLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('najm_hoda:autonomy:approval:requests');
    }

    public function test_edited_email_draft_is_frozen_and_exact_approved_version_is_sent(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);

        $draft = FounderEmailDraft::create([
            'recipients' => ['member@example.test'],
            'subject' => 'موضوع اولیه',
            'body' => '<p>متن اولیه</p>',
            'status' => 'draft',
            'reason_code' => 'email-lifecycle-test',
            'created_by' => $founder->id,
        ]);

        $expectedSender = [
            'address' => 'management@earthcoop.ir',
            'name' => 'تیم مدیریت EarthCoop',
            'reply_to' => 'management@earthcoop.ir',
        ];
        $delivery = Mockery::mock(EmailDeliveryService::class);
        $delivery->shouldReceive('sendHtml')
            ->once()
            ->with(['member@example.test'], 'موضوع نهایی مدیرکل', '<p>متن نهایی مدیرکل</p>', $expectedSender)
            ->andReturn([
                'sent_count' => 1,
                'failed_count' => 0,
                'recipients' => ['member@example.test'],
            ]);
        $this->app->instance(EmailDeliveryService::class, $delivery);

        $editing = app(FounderDraftEditingService::class);
        $decision = app(FounderEmailDecisionService::class);

        $edited = $editing->updateEmail(
            $draft,
            'موضوع نهایی مدیرکل',
            '<p>متن نهایی مدیرکل</p>',
            $founder->id
        );

        $this->assertTrue((bool) ($edited['success'] ?? false));
        $this->assertSame('updated', $edited['status']);
        $this->assertSame('موضوع نهایی مدیرکل', $draft->fresh()->subject);
        $this->assertSame('<p>متن نهایی مدیرکل</p>', $draft->fresh()->body);

        $prepared = $decision->requestSend($draft->fresh(), $founder->id);
        $this->assertSame('awaiting_approval', $prepared['status'] ?? null);
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $this->assertNotSame('', $requestId);

        $blocked = $editing->updateEmail(
            $draft->fresh(),
            'موضوعی که نباید اعمال شود',
            '<p>متنی که نباید اعمال شود</p>',
            $founder->id
        );

        $this->assertFalse((bool) ($blocked['success'] ?? true));
        $this->assertSame('pending_approval_must_be_decided_first', $blocked['reason']);
        $this->assertSame('موضوع نهایی مدیرکل', $draft->fresh()->subject);
        $this->assertSame('<p>متن نهایی مدیرکل</p>', $draft->fresh()->body);

        $result = $decision->decideAndExecute($requestId, 'approve', $founder->id, 'نسخه نهایی ایمیل تأیید شد');

        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('executed', $result['status']);
        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertSame($founder->id, (int) $draft->fresh()->approved_by);
        $this->assertNotNull($draft->fresh()->sent_at);
        $this->assertTrue((bool) data_get($result, 'verification.verified'));
        $this->assertSame('verified', (string) data_get($result, 'verification.status'));
        $this->assertSame(1, (int) data_get($result, 'result.recipient_count'));
        $this->assertSame(1, (int) data_get($result, 'result.sent_count'));
        $this->assertSame(0, (int) data_get($result, 'result.failed_count'));
        $this->assertSame($expectedSender['address'], data_get($result, 'result.sender_address'));
        $this->assertSame($expectedSender['name'], data_get($result, 'result.sender_name'));
    }
}
