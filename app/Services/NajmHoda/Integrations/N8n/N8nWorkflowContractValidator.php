<?php

namespace App\Services\NajmHoda\Integrations\N8n;

use InvalidArgumentException;

class N8nWorkflowContractValidator
{
    /**
     * Validate a workflow callback result without executing or interpreting it.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function validateResult(string $workflow, string $status, array $result): array
    {
        if ($workflow !== 'ops.health.read') {
            return $result;
        }

        if ($status === 'progress') {
            $this->assertOnlyKeys($result, ['phase', 'percent']);

            $phase = trim((string) ($result['phase'] ?? ''));
            $percent = (int) ($result['percent'] ?? -1);
            if ($phase === '' || strlen($phase) > 80 || $percent < 0 || $percent > 100) {
                throw new InvalidArgumentException('ops.health.read progress result is invalid.');
            }

            return ['phase' => $phase, 'percent' => $percent];
        }

        if ($status === 'failed') {
            $this->assertOnlyKeys($result, ['error_code']);
            $errorCode = trim((string) ($result['error_code'] ?? ''));
            if ($errorCode === '' || !preg_match('/^[A-Z0-9_\-]{2,64}$/', $errorCode)) {
                throw new InvalidArgumentException('ops.health.read failure result is invalid.');
            }

            return ['error_code' => $errorCode];
        }

        $this->assertOnlyKeys($result, ['healthy', 'observed_at', 'checks']);

        if (!array_key_exists('healthy', $result) || !is_bool($result['healthy'])) {
            throw new InvalidArgumentException('ops.health.read completed result requires boolean healthy.');
        }

        $observedAt = trim((string) ($result['observed_at'] ?? ''));
        if ($observedAt === '' || strtotime($observedAt) === false || strlen($observedAt) > 64) {
            throw new InvalidArgumentException('ops.health.read observed_at is invalid.');
        }

        $checks = $result['checks'] ?? [];
        if (!is_array($checks) || count($checks) > 20) {
            throw new InvalidArgumentException('ops.health.read checks are invalid.');
        }

        $normalizedChecks = [];
        foreach ($checks as $name => $passed) {
            $name = trim((string) $name);
            if ($name === '' || strlen($name) > 64 || !preg_match('/^[a-z0-9_.\-]+$/', $name) || !is_bool($passed)) {
                throw new InvalidArgumentException('ops.health.read check entry is invalid.');
            }
            $normalizedChecks[$name] = $passed;
        }

        return [
            'healthy' => $result['healthy'],
            'observed_at' => $observedAt,
            'checks' => $normalizedChecks,
        ];
    }

    /** @param array<string, mixed> $result */
    private function assertOnlyKeys(array $result, array $allowed): void
    {
        $unknown = array_diff(array_keys($result), $allowed);
        if (!empty($unknown)) {
            throw new InvalidArgumentException('n8n workflow result contains unsupported fields.');
        }
    }
}
