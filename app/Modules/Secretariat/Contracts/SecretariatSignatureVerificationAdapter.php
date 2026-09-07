<?php

namespace App\Modules\Secretariat\Contracts;

interface SecretariatSignatureVerificationAdapter
{
    public function provider(): string;

    /**
     * @param array<string,mixed> $evidence
     * @return array{verified:bool,provider_reference:?string,signer_name:string,signer_identifier:?string,metadata?:array<string,mixed>}
     */
    public function verify(string $manifestChecksum, array $evidence): array;
}
