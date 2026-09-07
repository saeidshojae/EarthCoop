<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatContractService;
use App\Modules\Secretariat\Services\SecretariatExportPackageService;
use App\Modules\Secretariat\Services\SecretariatIntegrityService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Modules\Secretariat\Services\SecretariatRetentionService;
use App\Modules\Secretariat\Services\SecretariatSignatureService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecretariatS9PermissionPenetrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_formal_services_reject_confused_deputy_calls(): void
    {
        [$manager, $inspector, $member, $outsider, $office] = $this->fixture();
        Storage::fake('local');
        $records = app(SecretariatRecordService::class);

        $record = $records->createDraft($office, $manager, [
            'record_type'=>'official_note','direction'=>'none','title'=>'Formal security target','body'=>'formal',
        ]);
        $record = $records->register($records->submitForApproval($record, $manager), $manager);
        $manifest = app(SecretariatIntegrityService::class)->generate($record->currentVersion, $manager);

        $this->assertAuthorizationDenied(fn () => app(SecretariatIntegrityService::class)->generate($record->currentVersion, $inspector));
        $this->assertAuthorizationDenied(fn () => app(SecretariatExportPackageService::class)->generate($manifest, $outsider, 'local'));
        $this->assertAuthorizationDenied(fn () => app(SecretariatSignatureService::class)->recordEvidence(
            $manifest, $inspector, 'seal', 'manual-test', 'Unauthorized Inspector'
        ));
        $this->assertAuthorizationDenied(fn () => app(SecretariatRetentionService::class)->placeHold(
            $record, $inspector, ['reason'=>'unauthorized hold']
        ));
        $this->assertAuthorizationDenied(fn () => app(SecretariatAclService::class)->grant(
            $record, 'user', $outsider->id, $member
        ));

        $this->assertDatabaseCount('secretariat_export_packages', 0);
        $this->assertDatabaseCount('secretariat_signature_attestations', 0);
        $this->assertDatabaseCount('secretariat_legal_holds', 0);
        $this->assertDatabaseCount('secretariat_acl_entries', 0);
    }

    public function test_acl_view_grant_does_not_escalate_to_formal_mutation_authority(): void
    {
        [$manager, , $member, , $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $manager, [
            'record_type'=>'official_note','direction'=>'none','title'=>'Confidential target','body'=>'secret','confidentiality'=>'confidential',
        ]);
        $record = $records->register($records->submitForApproval($record, $manager), $manager);
        app(SecretariatAclService::class)->grant($record, 'user', $member->id, $manager);

        $this->assertTrue($member->can('view', $record));
        $this->assertFalse($member->can('transition', $record));
        $this->assertAuthorizationDenied(fn () => app(SecretariatRetentionService::class)->assign(
            $record, $member, ['disposition'=>'preserve','reason'=>'attempted privilege escalation']
        ));
        $this->assertDatabaseCount('secretariat_retention_assignments', 0);
    }

    public function test_inspector_can_prepare_draft_contract_formality_but_member_cannot(): void
    {
        [$manager, $inspector, $member, , $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $contract = $records->createDraft($office, $manager, [
            'record_type'=>'contract','direction'=>'none','title'=>'Draft contract','body'=>'terms',
        ]);

        $details = app(SecretariatContractService::class)->putVersionDetails(
            $contract->currentVersion, $inspector, ['renewal_mode'=>'none']
        );
        $this->assertSame($contract->current_version_id, $details->record_version_id);

        $this->assertAuthorizationDenied(fn () => app(SecretariatContractService::class)->addParty(
            $contract, $member, ['party_type'=>'external','display_name'=>'Unauthorized party']
        ));
    }

    private function assertAuthorizationDenied(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Unauthorized Secretariat service call was accepted.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    private function fixture(): array
    {
        $manager = User::factory()->create();
        $inspector = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $group = Group::query()->create(['name'=>'S9 Penetration Group','group_type'=>'0']);
        foreach ([[$manager,3],[$inspector,2],[$member,1]] as [$user,$role]) {
            GroupUser::query()->create([
                'group_id'=>$group->id,'user_id'=>$user->id,'role'=>$role,'status'=>1,'expired'=>null,
            ]);
        }
        $office = app(SecretariatOfficeService::class)->create([
            'code'=>'S9-PEN-'.$group->id,'name'=>'S9 Penetration Office','office_type'=>'group','scope_type'=>'group','scope_id'=>$group->id,
        ]);
        return [$manager,$inspector,$member,$outsider,$office];
    }
}
