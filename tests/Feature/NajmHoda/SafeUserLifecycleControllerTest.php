<?php

namespace Tests\Feature\NajmHoda;

use App\Http\Controllers\Admin\SafeUserController;
use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SafeUserLifecycleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_controller_binding_resolves_safe_boundary_and_single_status_uses_lifecycle_service(): void
    {
        $controller=app(UserController::class);
        $this->assertInstanceOf(SafeUserController::class,$controller);

        $member=$this->user('member',false);
        $request=Request::create('/admin/users/'.$member->id.'/status','POST',['status'=>'suspended']);
        app()->instance('request',$request);

        $controller->updateStatus($request,$member);

        $this->assertSame('suspended',$member->fresh()->status);
    }

    public function test_bulk_status_change_updates_members_but_preserves_system_identity(): void
    {
        $controller=app(UserController::class);
        $member=$this->user('member',false);
        $system=$this->user('system',true);
        $request=Request::create('/admin/users/bulk-action','POST',[
            'action'=>'suspend',
            'user_ids'=>[$member->id,$system->id],
        ]);
        app()->instance('request',$request);

        $controller->bulkAction($request);

        $this->assertSame('suspended',$member->fresh()->status);
        $this->assertSame('active',$system->fresh()->status);
    }

    private function user(string $label,bool $system): User
    {
        return User::query()->create([
            'email'=>uniqid('safe-user-'.$label.'-',true).'@example.test',
            'password'=>bcrypt('password'),
            'status'=>'active',
            'first_name'=>'Safe',
            'last_name'=>ucfirst($label),
            'is_system'=>$system,
        ]);
    }
}
