<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $text = Str::lower($subject . ' ' . $message);

        // Determine priority
        $priority = 'normal';
        foreach ($this->priorityHigh as $kw) {
            if (Str::contains($text, Str::lower($kw))) {
                $priority = 'high';
                break;
            }
        }

        // Determine assignee by matching rules
        $assigneeId = null;

        foreach ($this->rules as $kw => $roleSlug) {
            if (Str::contains($text, Str::lower($kw))) {
                $assigneeId = $this->findOperatorByRole($roleSlug);
                if ($assigneeId) break;
            }
        }

        // If not found try fallback role
        if (!$assigneeId) {
            $assigneeId = $this->findOperatorByRole($this->fallbackRole);
        }

        return [
            'priority' => $priority,
            'assignee_id' => $assigneeId,
        ];
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
}
