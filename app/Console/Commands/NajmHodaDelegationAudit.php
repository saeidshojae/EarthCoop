<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaDelegatedPermissionService;
use Illuminate\Console\Command;

class NajmHodaDelegationAudit extends Command
{
    protected $signature = 'najm-hoda:delegation-audit
        {--actor= : actor id filter}
        {--action= : action filter}
        {--check : check authorization with actor+action}
        {--scope=global : scope for --check}';

    protected $description = 'Audit delegated permissions and optionally run authorization check';

    public function __construct(
        protected NajmHodaDelegatedPermissionService $delegationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $actor = is_numeric($this->option('actor')) ? (int) $this->option('actor') : null;
        $action = trim((string) ($this->option('action') ?? ''));

        if ((bool) $this->option('check')) {
            $result = $this->delegationService->authorize($actor, $action, (string) $this->option('scope'));
            $this->table(['Key', 'Value'], [
                ['allowed', ((bool) ($result['allowed'] ?? false)) ? 'yes' : 'no'],
                ['reason', (string) ($result['reason'] ?? '')],
                ['delegation_id', (string) ($result['delegation_id'] ?? '')],
            ]);
            return ((bool) ($result['allowed'] ?? false)) ? self::SUCCESS : self::FAILURE;
        }

        $rows = $this->delegationService->listActive($actor, $action === '' ? null : $action);
        $table = array_map(static function (array $row): array {
            return [
                (string) ($row['id'] ?? ''),
                (string) ($row['principal_type'] ?? ''),
                (string) ($row['principal_id'] ?? ''),
                (string) ($row['action'] ?? ''),
                (string) ($row['scope'] ?? ''),
                ((bool) ($row['require_approval'] ?? false)) ? 'yes' : 'no',
                (string) ($row['expires_at'] ?? ''),
            ];
        }, $rows);

        $this->table(['ID', 'PrincipalType', 'PrincipalID', 'Action', 'Scope', 'RequireApproval', 'ExpiresAt'], $table);
        return self::SUCCESS;
    }
}

