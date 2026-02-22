<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaModuleOnboardingAuditService;
use Illuminate\Console\Command;

class NajmHodaOnboardingAudit extends Command
{
    protected $signature = 'najm-hoda:onboarding-audit
        {--module= : Module key (e.g. najm_bahar)}
        {--prefix= : Runtime event prefix (e.g. najm_hoda.input.najm_bahar.service.)}
        {--window= : Window in hours}
        {--limit= : Number of recent events to inspect}
        {--fail-on-gap : Return non-zero when any automated check fails}';

    protected $description = 'Audit Phase-6 onboarding pattern for a domain/module';

    public function __construct(
        protected NajmHodaModuleOnboardingAuditService $auditService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Onboarding audit skipped.');
            return self::SUCCESS;
        }

        $module = trim((string) $this->option('module'));
        $prefix = trim((string) $this->option('prefix'));
        if ($module === '' || $prefix === '') {
            $this->error('Both --module and --prefix are required.');
            return self::FAILURE;
        }

        $window = $this->option('window');
        $limit = $this->option('limit');
        $windowHours = is_numeric($window) ? (int) $window : null;
        $eventLimit = is_numeric($limit) ? (int) $limit : null;

        $result = $this->auditService->audit($module, $prefix, $windowHours, $eventLimit);

        $checks = is_array($result['automated_checks'] ?? null) ? $result['automated_checks'] : [];

        $this->line('Najm Hoda Onboarding Audit');
        $this->table(
            ['Check', 'Status'],
            array_map(
                static fn (string $key): array => [$key, !empty($checks[$key]) ? 'ok' : 'gap'],
                array_keys($checks)
            )
        );

        $this->line(sprintf(
            'Automated score: %.2f (%d module events, prefix=%s)',
            (float) ($result['automated_score'] ?? 0.0),
            (int) ($result['module_event_count'] ?? 0),
            (string) ($result['prefix'] ?? '')
        ));

        $manual = is_array($result['manual_checklist'] ?? null) ? $result['manual_checklist'] : [];
        if (!empty($manual)) {
            $this->line('Manual checklist:');
            foreach ($manual as $key => $text) {
                $this->line(sprintf('- [%s] %s', (string) $key, (string) $text));
            }
        }

        $hasGap = in_array(false, array_values($checks), true);
        if ($hasGap && (bool) $this->option('fail-on-gap')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

