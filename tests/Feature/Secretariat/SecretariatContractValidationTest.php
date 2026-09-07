<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecretariatContractValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_record_type_is_rejected(): void
    {
        [$actor, $office] = $this->context();

        $this->expectException(ValidationException::class);
        app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'random_unregistered_type',
            'title' => 'Invalid',
        ]);
    }

    public function test_raw_class_name_or_unknown_source_token_is_rejected(): void
    {
        [$actor, $office] = $this->context();

        $this->expectException(ValidationException::class);
        app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'Invalid source',
            'source_type' => \App\Models\Group::class,
            'source_id' => 1,
        ]);
    }

    public function test_descriptor_source_cannot_smuggle_polymorphic_id(): void
    {
        [$actor, $office] = $this->context();

        $this->expectException(ValidationException::class);
        app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'External descriptor',
            'source_type' => 'external_document',
            'source_id' => 42,
        ]);
    }

    public function test_group_scope_has_only_one_canonical_secretariat_office(): void
    {
        $group = Group::query()->create(['name' => 'Canonical Office Group', 'group_type' => '0']);
        $service = app(SecretariatOfficeService::class);

        $service->create([
            'code' => 'GROUP-CANONICAL-1',
            'name' => 'Canonical Group Secretariat',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        $this->expectException(ValidationException::class);
        $service->create([
            'code' => 'GROUP-CANONICAL-2',
            'name' => 'Duplicate Group Secretariat',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
    }

    private function context(): array
    {
        $actor = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'CENTRAL',
            'name' => 'Central',
            'office_type' => 'central',
        ]);

        return [$actor, $office];
    }
}
