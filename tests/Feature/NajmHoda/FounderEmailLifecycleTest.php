<?php

namespace Tests\Feature\NajmHoda;

use App\Models\FounderEmailDraft;
use App\Models\User;
use App\Services\Email\EmailDeliveryService;
use App\Services\NajmHoda\FounderOps\FounderDraftEditingService;
use App\Services\NajmHoda\FounderOps\FounderEmailDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderEmailLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('najm_hoda:autonomy:approval:requests');
    }

    public function test_edited_email_is_frozen_and_sent_as_management_identity_after_founder_approval(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);

        $delivery = new class extends EmailDeliveryService {
            /** @var array<int,array<string,mixed>> */
            public array $calls = [];

            public function sendHtml(array $recipients, string $subject, string $body, ?array $from = null): array
            {
                $this->calls[] = compact('recipients', 'subject', 'body', 'from');
                return [
                    'sent_count' => count($recipients),
                    'failed_count' => 0,
                    'recipients' => $recipients,
                ];
            }
        };
        $this->app->instance(EmailDeliveryService::class, $delivery);

        $draft = FounderEmailDraft::query()->create([
            'recipients' => ['member@example.test'],
            'subject' => 'موضوع اولیه',
            'body' => 'متن اولیه',
            'status' => 'draft',
            'reason_code' => 'email-lifecycle-test',
        ]);

        $editing = app(FounderDraftEditingService::class);
        $decision = app(FounderEmailDecisionService::class);

        $edited = $editing->updateEmail(
            $draft,
            'موضوع ویرایش‌شده مدیرکل',
            'متن ویرایش‌شده و نهایی ایمیل',
            $founder->id
        );
        $this->assertTrue((bool) ($edited['success'] ?? false), json_encode($edited, JSON_UNESCAPED_UNICODE));

        $prepared = $decision->requestSend($draft->fresh(), $founder->id);
        $this->assertSame('awaiting_approval', $prepared['status'] ?? null, json_encode($prepared, JSON_UNESCAPED_UNICODE));
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $this->assertNotSame('', $requestId);

        $blocked = $editing->updateEmail(
            $draft->fresh(),
            'موضوع غیرمجاز پس از درخواست تأیید',
            'متن غیرمجاز پس از درخواست تأیید',
            $founder->id
        );
        $this->assertFalse((bool) ($blocked['success'] ?? true));
        $this->assertSame('pending_approval_must_be_decided_first', $blocked['reason']);

        $result = $decision->decideAndExecute($requestId, 'approve', $founder->id, 'ایمیل تأیید شد');

        $this->assertTrue((bool) ($result['success'] ?? false), json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->assertSame('executed', $result['status']);
        $this->assertTrue((bool) data_get($result, 'verification.verified'), json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertCount(1, $delivery->calls);

        $call = $delivery->calls[0];
        $this->assertSame(['member@example.test'], $call['recipients']);
        $this->assertSame('موضوع ویرایش‌شده مدیرکل', $call['subject']);
        $this->assertSame('متن ویرایش‌شده و نهایی ایمیل', $call['body']);
        $this->assertSame('management@earthcoop.ir', $call['from']['address']);
        $this->assertSame('تیم مدیریت EarthCoop', $call['from']['name']);
        $this->assertSame('management@earthcoop.ir', $call['from']['reply_to']);
    }
}
