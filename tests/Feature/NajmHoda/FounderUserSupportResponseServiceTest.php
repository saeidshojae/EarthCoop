<?php

namespace Tests\Feature\NajmHoda;

use App\Models\SupportReplyDraft;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderUserSupportResponseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderUserSupportResponseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.human_escalation.enabled' => true,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);
        Cache::flush();
    }

    public function test_user_support_send_requires_founder_approval_and_uses_canonical_ticket_reply(): void
    {
        $founder=$this->user('founder');
        $target=$this->user('target');
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids'=>[$founder->id]]);
        $ticket=$this->ticket($target);
        $draft=SupportReplyDraft::query()->create([
            'ticket_id'=>$ticket->id,
            'source'=>'najm_hoda',
            'body'=>'پاسخ پیشنهادی برای کاربر',
            'status'=>'draft',
        ]);
        $service=app(FounderUserSupportResponseService::class);

        $request=$service->requestSend($draft,$founder->id);
        $this->assertSame('awaiting_approval',$request['status']);
        $this->assertSame('draft',$draft->fresh()->status);
        $this->assertSame(0,$ticket->comments()->count());

        $requestId=(string)data_get($request,'approval_request.id','');
        $executed=$service->decideAndExecute($requestId,'approve',$founder->id,'Founder approved user support reply');

        $this->assertTrue($executed['success']);
        $this->assertSame('executed',$executed['status']);
        $this->assertSame('sent',$draft->fresh()->status);
        $this->assertSame(1,$ticket->comments()->count());
        $this->assertSame('in-progress',$ticket->fresh()->status);

        $report=app(FounderExecutiveConnectivityService::class)->report();
        $this->assertSame('managed',$report['domains']['users']['stage']);
        $this->assertSame('connected',data_get($report,'domains.users.actions.draft_support_response.state'));
        $this->assertSame('connected',data_get($report,'domains.users.actions.send_support_response.state'));
        $this->assertSame('connected',data_get($report,'domains.users.actions.suspend_user.state'));
    }

    public function test_rejection_does_not_send_user_support_response(): void
    {
        $founder=$this->user('founder');
        $target=$this->user('target');
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids'=>[$founder->id]]);
        $ticket=$this->ticket($target);
        $draft=SupportReplyDraft::query()->create([
            'ticket_id'=>$ticket->id,
            'source'=>'najm_hoda',
            'body'=>'پاسخ ردشدنی',
            'status'=>'draft',
        ]);
        $service=app(FounderUserSupportResponseService::class);

        $request=$service->requestSend($draft,$founder->id);
        $result=$service->decideAndExecute((string)data_get($request,'approval_request.id',''),'reject',$founder->id,'Do not send');

        $this->assertTrue($result['success']);
        $this->assertSame('rejected',$result['status']);
        $this->assertSame('rejected',$draft->fresh()->status);
        $this->assertSame(0,$ticket->comments()->count());
        $this->assertSame('open',$ticket->fresh()->status);
    }

    public function test_draft_is_blocked_when_ticket_does_not_belong_to_user(): void
    {
        $target=$this->user('target');
        $other=$this->user('other');
        $ticket=$this->ticket($other);

        $result=app(FounderUserSupportResponseService::class)->draft($target,$ticket);

        $this->assertFalse($result['success']);
        $this->assertSame('blocked',$result['status']);
        $this->assertSame('ticket_user_mismatch',$result['reason']);
        $this->assertSame(0,SupportReplyDraft::query()->count());
    }

    private function ticket(User $user): Ticket
    {
        return Ticket::query()->create([
            'user_id'=>$user->id,
            'tracking_code'=>'USR-'.strtoupper(substr(md5(uniqid('',true)),0,12)),
            'subject'=>'User support test',
            'message'=>'Need support',
            'status'=>'open',
            'priority'=>'normal',
        ]);
    }

    private function user(string $label): User
    {
        return User::query()->create([
            'email'=>uniqid('user-support-'.$label.'-',true).'@example.test',
            'password'=>bcrypt('password'),
            'status'=>'active',
            'first_name'=>'Support',
            'last_name'=>ucfirst($label),
            'is_system'=>false,
        ]);
    }
}
