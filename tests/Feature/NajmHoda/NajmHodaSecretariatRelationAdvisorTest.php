<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRelation;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Context\NajmHodaSecretariatRelationAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatRelationAdvisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_provenance_suggests_relation_without_mutation(): void
    {
        [$actor, $office, $group] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $resolution = $records->createDraft($office, $actor, [
            'record_type'=>'resolution','direction'=>'internal','title'=>'مصوبه آب','source_type'=>'manual',
        ]);
        $action = NajmHodaGroupActionItem::query()->create([
            'group_id'=>$group->id,
            'assigned_user_id'=>$actor->id,
            'title'=>'اجرای مصوبه آب',
            'priority'=>'high',
            'status'=>'done',
        ]);
        $report = $records->createDraft($office, $actor, [
            'record_type'=>'execution_record','direction'=>'internal','title'=>'گزارش اجرای آب',
            'source_type'=>'action_item','source_id'=>$action->id,
            'metadata'=>['s3_snapshot'=>['resolution_record_id'=>$resolution->id]],
        ]);

        $before = SecretariatRelation::query()->count();
        $result = app(NajmHodaSecretariatRelationAdvisor::class)->intercept(
            $actor,
            ['page_kind'=>'secretariat_record','resource_type'=>'secretariat_record','resource_id'=>$report->id],
            'رابطه این گزارش با کدام سند است؟'
        );

        $this->assertSame('relation_suggestions', $result['status']);
        $this->assertSame($before, SecretariatRelation::query()->count());
        $item = collect($result['relation_suggestions'])->first();
        $this->assertSame($resolution->id, $item['target_record_id']);
        $this->assertSame('report_of', $item['relation_type']);
        $this->assertSame('deterministic_provenance', $item['confidence']);
    }

    public function test_text_similarity_alone_never_creates_a_suggestion(): void
    {
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $records->createDraft($office, $actor, [
            'record_type'=>'resolution','direction'=>'internal','title'=>'برنامه آب محله','subject'=>'بحران آب','source_type'=>'manual',
        ]);
        $report = $records->createDraft($office, $actor, [
            'record_type'=>'execution_record','direction'=>'internal','title'=>'گزارش برنامه آب محله','subject'=>'بحران آب','source_type'=>'manual',
        ]);

        $result = app(NajmHodaSecretariatRelationAdvisor::class)->intercept(
            $actor,
            ['resource_type'=>'secretariat_record','resource_id'=>$report->id],
            'اسناد مرتبط این گزارش را پیدا کن'
        );

        $this->assertSame('no_relation_suggestion', $result['status']);
        $this->assertSame([], $result['relation_suggestions']);
        $this->assertDatabaseCount('secretariat_relations', 0);
    }

    public function test_existing_relation_is_not_suggested_twice(): void
    {
        [$actor, $office, $group] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $resolution = $records->createDraft($office, $actor, [
            'record_type'=>'resolution','direction'=>'internal','title'=>'مصوبه','source_type'=>'manual',
        ]);
        $action = NajmHodaGroupActionItem::query()->create([
            'group_id'=>$group->id,
            'assigned_user_id'=>$actor->id,
            'title'=>'اجرای مصوبه',
            'priority'=>'medium',
            'status'=>'done',
        ]);
        $report = $records->createDraft($office, $actor, [
            'record_type'=>'execution_record','direction'=>'internal','title'=>'گزارش',
            'source_type'=>'action_item','source_id'=>$action->id,
            'metadata'=>['s3_snapshot'=>['resolution_record_id'=>$resolution->id]],
        ]);
        app(\App\Modules\Secretariat\Services\SecretariatRelationService::class)->add($report, $resolution, 'report_of', $actor);

        $result = app(NajmHodaSecretariatRelationAdvisor::class)->intercept(
            $actor,
            ['resource_type'=>'secretariat_record','resource_id'=>$report->id],
            'رابطه این سند را پیشنهاد بده'
        );

        $this->assertSame('no_relation_suggestion', $result['status']);
        $this->assertDatabaseCount('secretariat_relations', 1);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['is_admin'=>1]);
        $group = Group::query()->create(['name'=>'Relation Advisor','group_type'=>'0']);
        $office = app(SecretariatOfficeService::class)->create([
            'code'=>'REL-'.$group->id,'name'=>'Relation Office','office_type'=>'group','scope_type'=>'group','scope_id'=>$group->id,
        ]);
        return [$actor, $office, $group];
    }
}
