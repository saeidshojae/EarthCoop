<?php

namespace Tests\Feature\NajmHoda;

use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderUserSuspensionDecisionService;
use App\Services\Users\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderUserSuspensionDecisionServiceTest extends TestCase
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

    public function test_suspend_user_requires_founder_approval_and_uses_canonical_service(): void
    {
        $founder=$this->user('founder');
        $target=$this->user('target');
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids'=>[$founder->id]]);
        $service=app(FounderUserSuspensionDecisionService::class);

        $request=$service->requestSuspend($target,$founder->id,'users-suspend-test-'.$target->id);

        $this->assertSame('awaiting_approval',$request['status']);
        $this->assertSame('active',$target->fresh()->status);
        $requestId=(string)data_get($request,'approval_request.id','');
        $this->assertNotSame('',$requestId);

        $executed=$service->decideAndExecute($requestId,'approve',$founder->id,'Founder approved suspension');

        $this->assertTrue($executed['success']);
        $this->assertSame('executed',$executed['status']);
        $this->assertSame('suspended',$target->fresh()->status);
        $this->assertSame('connected',data_get(app(FounderExecutiveConnectivityService::class)->report(),'domains.users.actions.suspend_user.state'));
    }

    public function test_rejection_and_unauthorized_decision_do_not_suspend_user(): void
    {
        $founder=$this->user('founder');
        $other=$this->user('other');
        $target=$this->user('target');
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids'=>[$founder->id]]);
        $service=app(FounderUserSuspensionDecisionService::class);

        $request=$service->requestSuspend($target,$founder->id,'users-suspend-reject-'.$target->id);
        $requestId=(string)data_get($request,'approval_request.id','');

        $forbidden=$service->decideAndExecute($requestId,'approve',$other->id);
        $this->assertFalse($forbidden['success']);
        $this->assertSame('forbidden',$forbidden['status']);
        $this->assertSame('active',$target->fresh()->status);

        $rejected=$service->decideAndExecute($requestId,'reject',$founder->id,'Do not suspend');
        $this->assertTrue($rejected['success']);
        $this->assertSame('rejected',$rejected['status']);
        $this->assertSame('active',$target->fresh()->status);
    }

    public function test_system_identity_is_protected_by_canonical_service_and_founder_adapter(): void
    {
        $founder=$this->user('founder');
        $system=$this->user('system',true);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids'=>[$founder->id]]);

        $canonical=app(UserManagementService::class)->suspend($system);
        $this->assertFalse($canonical['success']);
        $this->assertSame('system_identity_protected',$canonical['reason']);
        $this->assertSame('active',$system->fresh()->status);

        $request=app(FounderUserSuspensionDecisionService::class)->requestSuspend($system,$founder->id);
        $this->assertFalse($request['success']);
        $this->assertSame('blocked',$request['status']);
        $this->assertSame('system_identity_protected',$request['reason']);
        $this->assertSame('active',$system->fresh()->status);
    }

    private function user(string $label,bool $system=false): User
    {
        return User::query()->create([
            'email'=>uniqid('users-'.$label.'-',true).'@example.test',
            'password'=>bcrypt('password'),
            'status'=>'active',
            'first_name'=>'User',
            'last_name'=>ucfirst($label),
            'is_system'=>$system,
        ]);
    }
}
