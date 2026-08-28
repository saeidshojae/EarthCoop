<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Context\NajmHodaSecretariatReferralAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatReferralAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_has_zero_side_effect_and_confirmation_creates_pending_dispatch_only(): void
    {
        [$actor, $recipient, $record] = $this->fixture();
        $assistant = app(NajmHodaSecretariatReferralAssistant::class);
        $context = $this->context($record->id, $record->office_id);

        $preview = $assistant->intercept($actor, $context, "ارجاع بده | کاربر: {$recipient->id} | دستور: بررسی و اعلام نظر", 77);

        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertDatabaseCount('secretariat_dispatches', 0);

        $result = $assistant->intercept($actor, $context, 'تأیید ارجاع', 77);

        $this->assertSame('dispatch_pending', $result['status']);
        $this->assertDatabaseHas('secretariat_dispatches', [
            'record_id' => $record->id,
            'dispatch_type' => 'referral',
            'channel' => 'internal',
            'target_user_id' => $recipient->id,
            'status' => 'pending',
        ]);
        $dispatch = SecretariatDispatch::query()->firstOrFail();
        $this->assertNull($dispatch->dispatched_at);
        $this->assertNull($dispatch->received_at);
    }

    public function test_non_member_target_is_blocked_and_confirmation_rechecks_membership(): void
    {
        [$actor, $recipient, $record] = $this->fixture();
        $outsider = User::factory()->create();
        $assistant = app(NajmHodaSecretariatReferralAssistant::class);
        $context = $this->context($record->id, $record->office_id);

        $blocked = $assistant->intercept($actor, $context, "ارجاع بده | کاربر: {$outsider->id}", 78);
        $this->assertSame('blocked', $blocked['status']);
        $this->assertDatabaseCount('secretariat_dispatches', 0);

        $preview = $assistant->intercept($actor, $context, "ارجاع بده | کاربر: {$recipient->id}", 79);
        $this->assertSame('awaiting_confirmation', $preview['status']);

        GroupUser::query()->where('group_id', $record->office->scope_id)->where('user_id', $recipient->id)->delete();
        $stale = $assistant->intercept($actor, $context, 'تأیید ارجاع', 79);

        $this->assertSame('stale_preview', $stale['status']);
        $this->assertDatabaseCount('secretariat_dispatches', 0);
    }

    public function test_draft_record_cannot_be_referred(): void
    {
        [$actor, $recipient, $record] = $this->fixture(false);
        $assistant = app(NajmHodaSecretariatReferralAssistant::class);

        $result = $assistant->intercept($actor, $this->context($record->id, $record->office_id), "ارجاع بده | کاربر: {$recipient->id}", 80);

        $this->assertSame('blocked', $result['status']);
        $this->assertDatabaseCount('secretariat_dispatches', 0);
    }

    private function fixture(bool $register = true): array
    {
        $actor = User::factory()->create(['is_admin' => 1]);
        $recipient = User::factory()->create();
        $group = Group::query()->create(['name' => 'Referral Group', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $recipient->id,
            'role' => 0,
            'status' => 1,
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'REF-' . $group->id,
            'name' => 'Referral Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'Formal referral source',
            'body' => 'Body',
            'confidentiality' => 'office_members',
        ]);
        if ($register) {
            $records->submitForApproval($record, $actor);
            $record = $records->register($record->fresh(), $actor);
        }
        $record->load('office');

        return [$actor, $recipient, $record];
    }

    private function context(int $recordId, int $officeId): array
    {
        return [
            'page_kind' => 'secretariat_record',
            'resource_type' => 'secretariat_record',
            'resource_id' => $recordId,
            'resource' => ['office_id' => $officeId],
        ];
    }
}
