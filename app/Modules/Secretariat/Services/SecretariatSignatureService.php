<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Contracts\SecretariatSignatureVerificationAdapter;
use App\Modules\Secretariat\Models\SecretariatContractSignatory;
use App\Modules\Secretariat\Models\SecretariatIntegrityManifest;
use App\Modules\Secretariat\Models\SecretariatSignatureAttestation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecretariatSignatureService
{
    public function __construct(private readonly SecretariatAuditService $audit) {}

    /**
     * Records evidence without claiming cryptographic verification.
     *
     * @param array<string,mixed> $metadata
     */
    public function recordEvidence(
        SecretariatIntegrityManifest $manifest,
        User $actor,
        string $attestationType,
        string $provider,
        string $signerName,
        ?SecretariatContractSignatory $signatory = null,
        ?string $providerReference = null,
        array $metadata = [],
    ): SecretariatSignatureAttestation {
        return $this->persist(
            $manifest,
            $actor,
            $attestationType,
            $provider,
            $providerReference,
            $signerName,
            null,
            'recorded',
            null,
            $metadata,
            $signatory,
        );
    }

    /** @param array<string,mixed> $evidence */
    public function verifyWithAdapter(
        SecretariatIntegrityManifest $manifest,
        User $actor,
        string $attestationType,
        SecretariatSignatureVerificationAdapter $adapter,
        array $evidence,
        ?SecretariatContractSignatory $signatory = null,
    ): SecretariatSignatureAttestation {
        $result = $adapter->verify((string) $manifest->manifest_checksum, $evidence);
        $verified = (bool) ($result['verified'] ?? false);
        $signerName = trim((string) ($result['signer_name'] ?? ''));
        if ($signerName === '') {
            throw ValidationException::withMessages(['signer_name' => 'Signature adapter must return a signer name snapshot.']);
        }

        $identifier = isset($result['signer_identifier']) ? trim((string) $result['signer_identifier']) : null;
        $identifierHash = $identifier === null || $identifier === '' ? null : hash('sha256', $identifier);
        $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];

        return $this->persist(
            $manifest,
            $actor,
            $attestationType,
            $adapter->provider(),
            isset($result['provider_reference']) ? (string) $result['provider_reference'] : null,
            $signerName,
            $identifierHash,
            $verified ? 'verified' : 'rejected',
            $verified ? now() : null,
            $metadata,
            $signatory,
        );
    }

    /** @param array<string,mixed> $metadata */
    private function persist(
        SecretariatIntegrityManifest $manifest,
        User $actor,
        string $attestationType,
        string $provider,
        ?string $providerReference,
        string $signerName,
        ?string $signerIdentifierHash,
        string $verificationStatus,
        $verifiedAt,
        array $metadata,
        ?SecretariatContractSignatory $signatory,
    ): SecretariatSignatureAttestation {
        if (! in_array($attestationType, ['signature', 'seal'], true)) {
            throw ValidationException::withMessages(['attestation_type' => 'Attestation type must be signature or seal.']);
        }
        $provider = trim($provider);
        $signerName = trim($signerName);
        if ($provider === '' || mb_strlen($provider) > 120) {
            throw ValidationException::withMessages(['provider' => 'Signature provider is required and must be at most 120 characters.']);
        }
        if ($signerName === '' || mb_strlen($signerName) > 255) {
            throw ValidationException::withMessages(['signer_name' => 'Signer name snapshot is required and must be at most 255 characters.']);
        }

        $manifest = $manifest->fresh(['version.record.office']);
        if (! $manifest || ! $manifest->version?->is_official) {
            throw ValidationException::withMessages(['manifest' => 'Signature evidence requires an integrity manifest of an official version.']);
        }

        if ($signatory !== null && (int) $signatory->record_version_id !== (int) $manifest->record_version_id) {
            throw ValidationException::withMessages(['signatory' => 'Contract signatory must belong to the same version as the integrity manifest.']);
        }

        return DB::transaction(function () use ($manifest, $actor, $attestationType, $provider, $providerReference, $signerName, $signerIdentifierHash, $verificationStatus, $verifiedAt, $metadata, $signatory) {
            $locked = SecretariatIntegrityManifest::query()->with('version.record.office')->whereKey($manifest->id)->lockForUpdate()->firstOrFail();

            $attestation = SecretariatSignatureAttestation::query()->create([
                'manifest_id' => $locked->id,
                'contract_signatory_id' => $signatory?->id,
                'attestation_type' => $attestationType,
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'signer_name_snapshot' => $signerName,
                'signer_identifier_hash' => $signerIdentifierHash,
                'verification_status' => $verificationStatus,
                'verified_at' => $verifiedAt,
                'evidence_metadata' => $metadata ?: null,
                'created_by' => $actor->id,
            ]);

            $this->audit->append($locked->version->record->office, $locked->version->record, $actor, 'signature_attestation_recorded', [
                'manifest_id' => $locked->id,
                'attestation_id' => $attestation->id,
                'attestation_type' => $attestationType,
                'provider' => $provider,
                'verification_status' => $verificationStatus,
            ]);

            return $attestation;
        });
    }
}
