<?php

namespace Tests\Feature\NajmHoda;

use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Models\SecretariatFollowUpProposal;
use App\Modules\Secretariat\Services\SecretariatFollowUpProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderSecretariatFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminal_dispatch_does_not_create_follow_up_proposal(): void
    {
        $dispatch = new SecretariatDispatch(['status'=>'completed']);
        $dispatch->id = 999;

        $result = app(SecretariatFollowUpProposalService::class)->prepare($dispatch,'terminal-test');

        $this->assertFalse($result['success']);
        $this->assertSame('dispatch_terminal',$result['reason']);
        $this->assertSame(0, SecretariatFollowUpProposal::count());
    }
}
