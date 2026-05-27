<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaDelegatedPermissionService;
use Illuminate\Console\Command;

class NajmHodaDelegationGrant extends Command
{
    protected $signature = 'najm-hoda:delegation-grant
        {--principal-type=user : user|role|group}
        {--principal-id= : principal identifier}
        {--action= : delegated action}
        {--scope=global : delegation scope}
        {--expires-minutes=1440 : expiration minutes}
        {--require-approval : delegation requires approval}
        {--created-by= : creator user id}
        {--reason= : optional reason}';

    protected $description = 'Grant fine-grained delegated permission for Najm Hoda autonomy actions';

    public function __construct(
        protected NajmHodaDelegatedPermissionService $delegationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->delegationService->grant([
            'principal_type' => (string) $this->option('principal-type'),
            'principal_id' => (string) $this->option('principal-id'),
            'action' => (string) $this->option('action'),
            'scope' => (string) $this->option('scope'),
            'expires_in_minutes' => (int) $this->option('expires-minutes'),
            'require_approval' => (bool) $this->option('require-approval'),
            'created_by' => is_numeric($this->option('created-by')) ? (int) $this->option('created-by') : null,
            'reason' => $this->option('reason'),
        ]);

        if (!(bool) ($result['success'] ?? false)) {
            $this->warn('Delegation grant failed: ' . (string) ($result['reason'] ?? 'unknown'));
            return self::FAILURE;
        }

        $delegation = (array) ($result['delegation'] ?? []);
        $this->table(['Key', 'Value'], [
            ['id', (string) ($delegation['id'] ?? '')],
            ['principal_type', (string) ($delegation['principal_type'] ?? '')],
            ['principal_id', (string) ($delegation['principal_id'] ?? '')],
            ['action', (string) ($delegation['action'] ?? '')],
            ['scope', (string) ($delegation['scope'] ?? '')],
            ['require_approval', ((bool) ($delegation['require_approval'] ?? false)) ? 'yes' : 'no'],
            ['expires_at', (string) ($delegation['expires_at'] ?? '')],
        ]);

        return self::SUCCESS;
    }
}

