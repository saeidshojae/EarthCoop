<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\Context\NajmHodaSecretariatCaseAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatCaseAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_preview_is_pure_and_confirmation_creates_one_open_case_only(): void
    {
        [$actor, $office] = $this->context('S7-CASE');
        $pageContext = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatCaseAssistant::class);

        $preview = $assistant->intercept(
            $actor,
            $pageContext,
            'پرونده بساز | عنوان: پیگیری مسئله آب | خلاصه: گردآوری اسناد و مکاتبات مرتبط | محرمانگی: office_members',
            9301,
        );

        $this->assertIsArray($preview);
        $this->assertSame('secretariat_case', $preview['agent']);
        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertSame(0, SecretariatCase::query()->count());

        $created = $assistant->intercept($actor, $pageContext, 'ایجاد پرونده', 9301);

        $this->assertSame('case_created', $created['status']);
        $case = SecretariatCase::query()->sole();
        $this->assertSame('open', $case->status);
        $this->assertSame('پیگیری مسئله آب', $case->title);
        $this->assertSame('office_members', $case->confidentiality);
        $this->assertNotSame('', (string) $case->case_number);
        $this->assertSame('najm_hoda_s7', data_get($case->metadata, 'prepared_by'));
        $this->assertSame(0, $case->records()->count());
        $this->assertSame(0, SecretariatRecord::query()->count());
    }

    public function test_cancel_and_unauthorized_actor_create_no_case(): void
    {
        [$actor, $office] = $this->context('S7-CASE-CANCEL');
        $pageContext = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatCaseAssistant::class);

        $assistant->intercept(
            $actor,
            $pageContext,
            'پرونده بساز | عنوان: پرونده لغوشونده | خلاصه: نباید ایجاد شود',
            9302,
        );
        $cancelled = $assistant->intercept($actor, $pageContext, 'لغو', 9302);
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(0, SecretariatCase::query()->count());

        $outsider = User::factory()->create();
        $outsiderContext = $this->pageContext($outsider, $office->id);
        $blocked = $assistant->intercept(
            $outsider,
            $outsiderContext,
            'پرونده بساز | عنوان: غیرمجاز',
            9303,
        );
        $this->assertNull($blocked);
        $this->assertSame(0, SecretariatCase::query()->count());
    }

    /** @return array{0:User,1:mixed} */
    private function context(string $code): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => $code . ' Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        return [$actor, $office];
    }

    private function pageContext(User $actor, int $officeId): array
    {
        return app(NajmHodaPageContextResolver::class)->resolve($actor, [
            'page' => [
                'route_name' => 'secretariat.cases.create',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_office',
                'resource_id' => $officeId,
                'title' => 'FORGED TITLE',
                'body' => 'FORGED BODY',
            ],
        ]);
    }
}
