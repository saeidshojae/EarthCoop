<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Ticket;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderSupportTicketDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderSupportTicketDecisionServiceTest extends TestCase
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

    public function test_close_ticket_requires_founder_approval_and_uses_canonical_close(): void
    {
        $founder=$this->user();
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids'=>[$founder->id]]);
        $ticket=$this->ticket();
        $service=app(FounderSupportTicketDecisionService::class);

        $request=$service->requestClose($ticket,$founder->id,'support-close-test-'.$ticket->id);

        $this->assertSame('awaiting_approval',$request['status']);
        $this->assertSame('open',$ticket->fresh()->status);
        $requestId=(string)data_get($request,'approval_request.id','');
        $this->assertNotSame('',$requestId);

        $executed=$service->decideAndExecute($requestId,'approve',$founder->id,'Founder approved closure');

        $this->assertTrue($executed['success']);
        $this->assertSame('executed',$executed['status']);
        $this->assertSame('closed',$ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->assertSame('connected',data_get(app(FounderExecutiveConnectivityService::class)->report(),'domains.support.actions.close_ticket.state'));
    }

    public function test_rejection_and_unauthorized_decision_do_not_close_ticket(): void
    {
        $founder=$this->user();
        $other=$this->user();
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids'=>[$founder->id]]);
        $ticket=$this->ticket();
        $service=app(FounderSupportTicketDecisionService::class);

        $request=$service->requestClose($ticket,$founder->id,'support-close-reject-'.$ticket->id);
        $requestId=(string)data_get($request,'approval_request.id','');

        $forbidden=$service->decideAndExecute($requestId,'approve',$other->id);
        $this->assertFalse($forbidden['success']);
        $this->assertSame('forbidden',$forbidden['status']);
        $this->assertSame('open',$ticket->fresh()->status);

        $rejected=$service->decideAndExecute($requestId,'reject',$founder->id,'Keep ticket open');
        $this->assertTrue($rejected['success']);
        $this->assertSame('rejected',$rejected['status']);
        $this->assertSame('open',$ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    private function ticket(): Ticket
    {
        return Ticket::query()->create([
            'tracking_code'=>'NH-'.strtoupper(substr(md5(uniqid('',true)),0,12)),
            'subject'=>'Support test',
            'message'=>'Ticket body',
            'status'=>'open',
        ]);
    }

    private function user(): User
    {
        return User::query()->create([
            'email'=>uniqid('support-founder-',true).'@example.test',
            'password'=>bcrypt('password'),
            'status'=>1,
            'first_name'=>'Support',
            'last_name'=>'Founder',
            'is_system'=>false,
        ]);
    }
}
