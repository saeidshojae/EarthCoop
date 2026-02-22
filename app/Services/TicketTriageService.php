<?php

namespace App\Services;

use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TicketTriageService
{
    protected $rules;
    protected $priorityHigh;
    protected $fallbackRole;

    public function __construct()
    {
        $this->rules = config('support.triage_rules', []);
        $this->priorityHigh = config('support.priority_high_keywords', []);
        $this->fallbackRole = config('support.fallback_operator_role', 'support');
    }

    /**
     * Analyze subject+message and return an array with keys:
     * - priority: 'high'|'normal'
     * - assignee_id: user id or null
     */
    public function triage(string $subject, string $message): array
    {
        $context = [
            'scope' => 'support:tickets',
            'risk' => 'low',
            'subject_length' => mb_strlen($subject),
            'message_length' => mb_strlen($message),
        ];
        $this->emitRuntime('najm_hoda.input.support.service.ticket_triage.requested', $context);

        try {
            $text = Str::lower($subject . ' ' . $message);

            $priority = 'normal';
            foreach ($this->priorityHigh as $kw) {
                if (Str::contains($text, Str::lower($kw))) {
                    $priority = 'high';
                    break;
                }
            }

            $assigneeId = null;

            foreach ($this->rules as $kw => $roleSlug) {
                if (Str::contains($text, Str::lower($kw))) {
                    $assigneeId = $this->findOperatorByRole($roleSlug);
                    if ($assigneeId) {
                        break;
                    }
                }
            }

            if (!$assigneeId) {
                $assigneeId = $this->findOperatorByRole($this->fallbackRole);
            }

            $result = [
                'priority' => $priority,
                'assignee_id' => $assigneeId,
            ];
            $this->emitRuntime('najm_hoda.input.support.service.ticket_triage.succeeded', array_merge($context, [
                'priority' => $priority,
                'assignee_found' => $assigneeId !== null,
            ]));

            return $result;
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.support.service.ticket_triage.failed', array_merge($context, [
                'error' => $e->getMessage(),
                'risk' => 'medium',
            ]));

            throw $e;
        }
    }

    protected function findOperatorByRole(string $roleSlug)
    {
        // Guard if roles/users tables don't exist (support both role_user and user_role pivots)
        if (! Schema::hasTable('roles') || (! Schema::hasTable('role_user') && ! Schema::hasTable('user_role') && ! Schema::hasTable('model_has_roles'))) {
            return null;
        }

        // Try common schemas: spatie (model_has_roles) or custom pivot role_user
        if (Schema::hasTable('model_has_roles')) {
            $role = DB::table('roles')->where('slug', $roleSlug)->orWhere('name', $roleSlug)->first();
            if (!$role) return null;

            $modelRole = DB::table('model_has_roles')->where('role_id', $role->id)->first();
            if ($modelRole) {
                // model_id is the user id for Spatie setup
                return $modelRole->model_id;
            }

            return null;
        }

        // fallback: role_user or user_role pivot (project uses `user_role`)
        $pivotRoleUser = null;
        if (Schema::hasTable('role_user')) {
            $pivotRoleUser = 'role_user';
        } elseif (Schema::hasTable('user_role')) {
            $pivotRoleUser = 'user_role';
        }

        if ($pivotRoleUser) {
            $role = DB::table('roles')->where('slug', $roleSlug)->orWhere('name', $roleSlug)->first();
            if (!$role) return null;

            $ru = DB::table($pivotRoleUser)->where('role_id', $role->id)->first();
            if ($ru) return $ru->user_id;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function emitRuntime(string $event, array $payload): void
    {
        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);

            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // no-op
        }
    }
}
