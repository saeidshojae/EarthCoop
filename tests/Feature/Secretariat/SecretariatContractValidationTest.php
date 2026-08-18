<?php

namespace Tests\Feature\Secretariat;

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
