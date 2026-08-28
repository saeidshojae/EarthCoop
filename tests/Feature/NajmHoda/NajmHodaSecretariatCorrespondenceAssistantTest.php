<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\Context\NajmHodaSecretariatCorrespondenceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatCorrespondenceAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_outgoing_letter_is_previewed_before_exact_draft_persistence(): void
    {
        [$actor, $group, $office] = $this->context('S7-CORR');
        $pageContext = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatCorrespondenceAssistant::class);

        $preview = $assistant->intercept(
            $actor,
            $pageContext,
            'پیش‌نویس نامه صادره بساز | گیرنده: مرکز پژوهش نمونه | سازمان: موسسه نمونه | ایمیل: office@example.org | عنوان: درخواست همکاری | موضوع: همکاری پژوهشی | متن: خواهشمند است زمینه همکاری مشترک بررسی شود. | کانال: email | محرمانگی: office_members',
            9001,
        );

        $this->assertIsArray($preview);
        $this->assertSame('secretariat_correspondence', $preview['agent']);
        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());

        $saved = $assistant->intercept($actor, $pageContext, 'ذخیره نامه', 9001);

        $this->assertIsArray($saved);
        $this->assertSame('draft_saved', $saved['status']);
        $record = SecretariatRecord::query()->with(['parties', 'correspondenceDetail'])->sole();
        $this->assertSame('draft', $record->status);
        $this->assertSame('outgoing_letter', $record->record_type);
        $this->assertSame('outgoing', $record->direction);
        $this->assertNull($record->registry_number);
        $this->assertSame('درخواست همکاری', $record->title);
        $this->assertSame('email', $record->correspondenceDetail?->channel);
        $this->assertCount(2, $record->parties);
        $this->assertSame($group->id, $record->parties()->where('role', 'sender')->value('group_id'));
        $this->assertSame('مرکز پژوهش نمونه', $record->parties()->where('role', 'recipient')->value('display_name'));
        $this->assertSame('office@example.org', $record->parties()->where('role', 'recipient')->value('email'));
        $this->assertSame(0, $record->dispatches()->count());
    }

    public function test_cancel_and_unauthorized_actor_never_create_correspondence(): void
    {
        [$actor, , $office] = $this->context('S7-CANCEL');
        $pageContext = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatCorrespondenceAssistant::class);

        $assistant->intercept(
            $actor,
            $pageContext,
            'پیش‌نویس نامه صادره بساز | گیرنده: سازمان بیرونی | عنوان: نامه لغوشونده | متن: این نامه نباید ذخیره شود. | کانال: email',
            9002,
        );
        $cancelled = $assistant->intercept($actor, $pageContext, 'لغو', 9002);
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());

        $outsider = User::factory()->create();
        $outsiderContext = $this->pageContext($outsider, $office->id);
        $blocked = $assistant->intercept(
            $outsider,
            $outsiderContext,
            'پیش‌نویس نامه صادره بساز | گیرنده: سازمان بیرونی | عنوان: غیرمجاز | متن: نباید ساخته شود. | کانال: email',
            9003,
        );

        // The server resolver withholds the office resource entirely from an
        // unauthorized outsider, so the assistant has no authority-bearing target.
        $this->assertNull($blocked);
        $this->assertSame(0, SecretariatRecord::query()->count());
    }

    /** @return array{0:User,1:Group,2:mixed} */
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
        return [$actor, $group, $office];
    }

    private function pageContext(User $actor, int $officeId): array
    {
        return app(NajmHodaPageContextResolver::class)->resolve($actor, [
            'page' => [
                'route_name' => 'secretariat.correspondence.create',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_office',
                'resource_id' => $officeId,
                'title' => 'FORGED BROWSER TITLE',
                'body' => 'FORGED BROWSER BODY',
            ],
        ]);
    }
}
