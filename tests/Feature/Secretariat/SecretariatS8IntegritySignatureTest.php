<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Contracts\SecretariatSignatureVerificationAdapter;
use App\Modules\Secretariat\Models\SecretariatIntegrityManifest;
use App\Modules\Secretariat\Models\SecretariatSignatureAttestation;
use App\Modules\Secretariat\Services\SecretariatIntegrityService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Modules\Secretariat\Services\SecretariatSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatS8IntegritySignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_generated_only_for_official_version_and_verifies_against_current_package(): void
    {
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $integrity = app(SecretariatIntegrityService::class);

        $record = $records->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'title' => 'گزارش رسمی',
            'body' => 'محتوای ثابت',
        ]);

        try {
            $integrity->generate($record->currentVersion, $actor);
            $this->fail('Manifest was generated for a draft version.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('secretariat_integrity_manifests', 0);
        }

        $record = $records->register($records->submitForApproval($record, $actor), $actor);
        $manifest = $integrity->generate($record->currentVersion, $actor);
        $verification = $integrity->verify($manifest);

        $this->assertSame(1, $manifest->manifest_sequence);
        $this->assertSame(64, strlen($manifest->manifest_checksum));
        $this->assertTrue($verification['stored_payload_valid']);
        $this->assertTrue($verification['current_version_matches']);
    }

    public function test_manifest_and_attestation_rows_are_append_only(): void
    {
        [$actor, $office] = $this->fixture();
        $record = app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'یادداشت رسمی',
        ]);
        $record = app(SecretariatRecordService::class)->register(
            app(SecretariatRecordService::class)->submitForApproval($record, $actor),
            $actor
        );
        $manifest = app(SecretariatIntegrityService::class)->generate($record->currentVersion, $actor);
        $attestation = app(SecretariatSignatureService::class)->recordEvidence(
            $manifest,
            $actor,
            'seal',
            'manual-evidence',
            'دبیرخانه مرکزی',
            null,
            'seal-reference-1',
            ['note' => 'Evidence recorded without cryptographic verification']
        );

        $this->assertSame('recorded', $attestation->verification_status);
        $this->assertNull($attestation->verified_at);

        try {
            $manifest->manifest_checksum = str_repeat('0', 64);
            $manifest->save();
            $this->fail('Manifest mutation was allowed.');
        } catch (LogicException) {
            $this->assertNotSame(str_repeat('0', 64), $manifest->fresh()->manifest_checksum);
        }

        $this->expectException(LogicException::class);
        $attestation->delete();
    }

    public function test_verified_status_can_only_come_from_verification_adapter_result(): void
    {
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'title' => 'گزارش امضاشده',
        ]);
        $record = $records->register($records->submitForApproval($record, $actor), $actor);
        $manifest = app(SecretariatIntegrityService::class)->generate($record->currentVersion, $actor);

        $adapter = new class implements SecretariatSignatureVerificationAdapter {
            public function provider(): string { return 'test-qualified-provider'; }
            public function verify(string $manifestChecksum, array $evidence): array
            {
                return [
                    'verified' => hash_equals($manifestChecksum, (string) ($evidence['manifest_checksum'] ?? '')),
                    'provider_reference' => 'verified-123',
                    'signer_name' => 'امضاکننده آزمون',
                    'signer_identifier' => 'sensitive-identity-123',
                    'metadata' => ['adapter' => 'test'],
                ];
            }
        };

        $verified = app(SecretariatSignatureService::class)->verifyWithAdapter(
            $manifest,
            $actor,
            'signature',
            $adapter,
            ['manifest_checksum' => $manifest->manifest_checksum]
        );

        $this->assertSame('verified', $verified->verification_status);
        $this->assertNotNull($verified->verified_at);
        $this->assertSame('test-qualified-provider', $verified->provider);
        $this->assertSame(64, strlen((string) $verified->signer_identifier_hash));
        $this->assertDatabaseMissing('secretariat_signature_attestations', [
            'signer_identifier_hash' => 'sensitive-identity-123',
        ]);
    }

    public function test_rejected_adapter_verification_is_recorded_as_rejected_not_verified(): void
    {
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'یادداشت',
        ]);
        $record = $records->register($records->submitForApproval($record, $actor), $actor);
        $manifest = app(SecretariatIntegrityService::class)->generate($record->currentVersion, $actor);

        $adapter = new class implements SecretariatSignatureVerificationAdapter {
            public function provider(): string { return 'rejecting-provider'; }
            public function verify(string $manifestChecksum, array $evidence): array
            {
                return ['verified' => false, 'provider_reference' => 'bad-1', 'signer_name' => 'Unknown signer', 'signer_identifier' => null];
            }
        };

        $result = app(SecretariatSignatureService::class)->verifyWithAdapter($manifest, $actor, 'signature', $adapter, []);

        $this->assertSame('rejected', $result->verification_status);
        $this->assertNull($result->verified_at);
        $this->assertSame(0, SecretariatSignatureAttestation::query()->where('verification_status', 'verified')->count());
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['is_admin' => 1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S8-INTEGRITY',
            'name' => 'S8 Integrity Office',
            'office_type' => 'central',
        ]);
        return [$actor, $office];
    }
}
