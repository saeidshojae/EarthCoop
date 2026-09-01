<?php

namespace App\Console\Commands;

use App\Modules\Secretariat\Services\SecretariatOperationalHealthService;
use Illuminate\Console\Command;

class SecretariatHealthCheck extends Command
{
    protected $signature = 'secretariat:health
        {--deep : Verify bounded storage objects and integrity manifests}
        {--limit=200 : Maximum objects/manifests checked in deep mode}
        {--fail-on-issues : Exit non-zero when deep integrity issues are detected}';

    protected $description = 'Emit deterministic Secretariat operational health metrics and optional bounded integrity diagnostics.';

    public function handle(SecretariatOperationalHealthService $health): int
    {
        $payload = ['snapshot' => $health->snapshot()];

        if ((bool) $this->option('deep')) {
            $payload['deep_audit'] = $health->deepAudit((int) $this->option('limit'));
        }

        $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        if ((bool) $this->option('fail-on-issues')
            && isset($payload['deep_audit'])
            && ! (bool) ($payload['deep_audit']['healthy'] ?? false)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
