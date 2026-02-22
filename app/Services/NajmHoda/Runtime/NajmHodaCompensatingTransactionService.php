<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\Ticket;
use App\Modules\NajmBahar\Models\Project as NajmBaharProject;

class NajmHodaCompensatingTransactionService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    public function execute(array $step, string $runId): array
    {
        $comp = is_array($step['compensation'] ?? null) ? $step['compensation'] : [];
        $type = strtolower(trim((string) ($comp['type'] ?? '')));
        if ($type === '') {
            return ['handled' => false, 'status' => 'skipped', 'reason' => 'no_compensation_spec'];
        }

        return match ($type) {
            'ticket_status_revert' => $this->revertTicketStatus($step, $runId, $comp),
            'project_status_revert' => $this->revertProjectStatus($step, $runId, $comp),
            default => ['handled' => false, 'status' => 'skipped', 'reason' => 'unknown_compensation_type'],
        };
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed> $comp
     * @return array<string, mixed>
     */
    protected function revertTicketStatus(array $step, string $runId, array $comp): array
    {
        $ticketId = (int) ($comp['ticket_id'] ?? 0);
        if ($ticketId <= 0) {
            return ['handled' => true, 'status' => 'failed', 'reason' => 'missing_ticket_id'];
        }

        $previousStatus = trim((string) ($comp['previous_status'] ?? ''));
        if ($previousStatus === '' && (bool) ($comp['use_execution_context_previous_status'] ?? true)) {
            $previousStatus = trim((string) data_get($step, 'execution_context.previous_status', ''));
        }
        if ($previousStatus === '') {
            return ['handled' => true, 'status' => 'failed', 'reason' => 'missing_previous_status'];
        }

        try {
            $ticket = Ticket::query()->find($ticketId);
            if ($ticket === null) {
                return ['handled' => true, 'status' => 'failed', 'reason' => 'ticket_not_found'];
            }

            $ticket->status = $previousStatus;
            $ticket->save();

            $this->eventBus->emit('najm_hoda.autonomy.orchestrator.compensation.executed', [
                'run_id' => $runId,
                'type' => 'ticket_status_revert',
                'ticket_id' => $ticketId,
                'restored_status' => $previousStatus,
            ]);

            return ['handled' => true, 'status' => 'executed', 'reason' => 'ticket_status_restored'];
        } catch (\Throwable $e) {
            $this->eventBus->emit('najm_hoda.autonomy.orchestrator.compensation.failed', [
                'run_id' => $runId,
                'type' => 'ticket_status_revert',
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);

            return ['handled' => true, 'status' => 'failed', 'reason' => 'ticket_revert_exception'];
        }
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed> $comp
     * @return array<string, mixed>
     */
    protected function revertProjectStatus(array $step, string $runId, array $comp): array
    {
        $projectId = (int) ($comp['project_id'] ?? 0);
        if ($projectId <= 0) {
            return ['handled' => true, 'status' => 'failed', 'reason' => 'missing_project_id'];
        }

        $previousStatus = trim((string) ($comp['previous_status'] ?? ''));
        if ($previousStatus === '' && (bool) ($comp['use_execution_context_previous_status'] ?? true)) {
            $previousStatus = trim((string) data_get($step, 'execution_context.previous_status', ''));
        }
        if ($previousStatus === '') {
            return ['handled' => true, 'status' => 'failed', 'reason' => 'missing_previous_status'];
        }

        try {
            $project = NajmBaharProject::query()->find($projectId);
            if ($project === null) {
                return ['handled' => true, 'status' => 'failed', 'reason' => 'project_not_found'];
            }

            $project->status = $previousStatus;
            $project->save();

            $this->eventBus->emit('najm_hoda.autonomy.orchestrator.compensation.executed', [
                'run_id' => $runId,
                'type' => 'project_status_revert',
                'project_id' => $projectId,
                'restored_status' => $previousStatus,
            ]);

            return ['handled' => true, 'status' => 'executed', 'reason' => 'project_status_restored'];
        } catch (\Throwable $e) {
            $this->eventBus->emit('najm_hoda.autonomy.orchestrator.compensation.failed', [
                'run_id' => $runId,
                'type' => 'project_status_revert',
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return ['handled' => true, 'status' => 'failed', 'reason' => 'project_revert_exception'];
        }
    }
}

