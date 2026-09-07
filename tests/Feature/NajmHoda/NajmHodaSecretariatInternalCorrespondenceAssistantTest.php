<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\Context\NajmHodaSecretariatInternalCorrespondenceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatInternalCorrespondenceAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_correspondence_resolves_group_recipient_and_saves_only_after_confirmation(): void
    {
        [$actor, $recipient, $group, $office] = $this->context('S7-INTERNAL');
        $pageContext = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatInternalCorrespondenceAssistant::class);

        $preview = $assistant->intercept(
            $actor,
            $pageContext,
            'پیش‌نویس مکاتبه داخلی بساز | گیرنده کاربر: ' . $recipient->id . ' | عنوان: پیگیری مصوبه | موضوع: اقدام اجرایی | متن: لطفاً وضعیت اقدام مصوب را تا جلسه آینده گزارش کنید. | کانال: internal | محرمانگی: office_members',
            9201,
        );

        $this->assertIsArray($preview);
        $this->assertSame('secretariat_internal_correspondence', $preview['agent']);
        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertStringContainsString((string) $recipient->id, $preview['message']);
        $this->assertSame(0, SecretariatRecord::query()->count());

        $saved = $assistant->intercept($actor, $pageContext, 'ذخیره مکاتبه', 9201);
        $this->assertSame('draft_saved', $saved['status']);

        $record = SecretariatRecord::query()->with(['parties', 'correspondenceDetail'])->sole();
        $this->assertSame('draft', $record->status);
        $this->assertSame('internal_correspondence', $record->record_type);
        $this->assertSame('internal', $record->direction);
        $this->assertNull($record->registry_number);
        $this->assertSame('manual', $record->source_type);
        $this->assertSame($group->id, $record->parties()->where('role', 'sender')->value('group_id'));
        $this->assertSame($recipient->id, $record->parties()->where('role', 'recipient')->value('user_id'));
        $this->assertSame('internal', $record->correspondenceDetail?->channel);
        $this->assertSame(0, $record->dispatches()->count());
    }

    public function test_non_member_recipient_and_cancel_create_no_record(): void
    {
        [$actor, , , $office] = $this->context('S7-INTERNAL-BLOCK');
        $outsider = User::factory()->create();
        $pageContext = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatInternalCorrespondenceAssistant::class);

        $needsInput = $assistant->intercept(
            $actor,
            $pageContext,
            'پیش‌نویس مکاتبه داخلی بساز | گیرنده کاربر: ' . $outsider->id . ' | عنوان: نامعتبر | متن: نباید ساخته شود.',
            9202,
        );
        $this->assertSame('needs_input', $needsInput['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());

        $recipient = User::factory()->create();
        GroupUser::query()->create([
            'group_id' => $office->scope_id,
            'user_id' => $recipient->id,
            'role' => 1,
            'status' => 1,
            'expired' => null,
        ]);
        $assistant->intercept(
            $actor,
            $pageContext,
            'پیش‌نویس مکاتبه داخلی بساز | گیرنده کاربر: ' . $recipient->id . ' | عنوان: لغوشونده | متن: نباید ذخیره شود.',
            9203,
        );
        $cancelled = $assistant->intercept($actor, $pageContext, 'لغو', 9203);
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());
    }

    /** @return array{0:User,1:User,2:Group,3:mixed} */
    private function context(string $code): array
    {
        $actor = User::factory()->create();
        $recipient = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);
        foreach ([[$actor, 3], [$recipient, 1]] as [$user, $role]) {
            GroupUser::query()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 1,
                'expired' => null,
            ]);
        }
        $office = app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => $code . ' Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        return [$actor, $recipient, $group, $office];
    }

    private function pageContext(User $actor, int $officeId): array
    {
        return app(NajmHodaPageContextResolver::class)->resolve($actor, [
            'page' => [
                'route_name' => 'secretariat.correspondence.create',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_office',
                'resource_id' => $officeId,
            ],
        ]);
    }
}
