<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\Context\NajmHodaSecretariatIncomingCorrespondenceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatIncomingCorrespondenceAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_letter_requires_preview_then_persists_external_sender_snapshot(): void
    {
        [$actor, $group, $office] = $this->context('S7-INCOMING');
        $pageContext = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatIncomingCorrespondenceAssistant::class);

        $preview = $assistant->intercept(
            $actor,
            $pageContext,
            'پیش‌نویس نامه وارده بساز | فرستنده: بنیاد نمونه | سازمان: بنیاد نمونه | ایمیل: inbound@example.org | عنوان: پیشنهاد همکاری | موضوع: همکاری اجتماعی | متن: متن نامه دریافتی برای بررسی ثبت می‌شود. | دریافت: 2026-08-19T12:00:00+04:00 | شماره خارجی: EXT-2026-42 | کانال: email | محرمانگی: office_members',
            9101,
        );

        $this->assertIsArray($preview);
        $this->assertSame('secretariat_incoming_correspondence', $preview['agent']);
        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());

        $saved = $assistant->intercept($actor, $pageContext, 'ذخیره نامه', 9101);
        $this->assertSame('draft_saved', $saved['status']);

        $record = SecretariatRecord::query()->with(['parties', 'correspondenceDetail'])->sole();
        $this->assertSame('draft', $record->status);
        $this->assertSame('incoming_letter', $record->record_type);
        $this->assertSame('incoming', $record->direction);
        $this->assertNull($record->registry_number);
        $this->assertSame('external_document', $record->source_type);
        $this->assertSame('EXT-2026-42', $record->correspondenceDetail?->external_reference_number);
        $this->assertNotNull($record->correspondenceDetail?->received_at);
        $this->assertNull($record->correspondenceDetail?->sent_at);
        $this->assertSame('email', $record->correspondenceDetail?->channel);
        $this->assertSame('بنیاد نمونه', $record->parties()->where('role', 'sender')->value('display_name'));
        $this->assertSame($group->id, $record->parties()->where('role', 'recipient')->value('group_id'));
        $this->assertSame(0, $record->dispatches()->count());
    }

    public function test_invalid_or_cancelled_incoming_request_creates_no_record(): void
    {
        [$actor, , $office] = $this->context('S7-IN-CANCEL');
        $pageContext = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatIncomingCorrespondenceAssistant::class);

        $needsInput = $assistant->intercept(
            $actor,
            $pageContext,
            'پیش‌نویس نامه وارده بساز | فرستنده: بیرونی | عنوان: بدون زمان | متن: زمان دریافت مشخص نشده است.',
            9102,
        );
        $this->assertSame('needs_input', $needsInput['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());

        $assistant->intercept(
            $actor,
            $pageContext,
            'پیش‌نویس نامه وارده بساز | فرستنده: بیرونی | عنوان: لغوشونده | متن: نباید ذخیره شود. | دریافت: 2026-08-19T13:00:00+04:00',
            9103,
        );
        $cancelled = $assistant->intercept($actor, $pageContext, 'لغو', 9103);
        $this->assertSame('cancelled', $cancelled['status']);
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
            ],
        ]);
    }
}
