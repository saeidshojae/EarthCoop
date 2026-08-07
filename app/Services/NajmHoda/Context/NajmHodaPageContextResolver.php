<?php

namespace App\Services\NajmHoda\Context;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Converts narrow browser page hints into server-validated, read-only context.
 *
 * Browser values are never treated as authority. Resource details are resolved
 * from the database and are only returned when the authenticated viewer may
 * already see that resource. Free-form resource text is deliberately excluded
 * from model context at this trust boundary.
 */
class NajmHodaPageContextResolver
{
    /**
     * @param array<string, mixed> $browserContext
     * @return array<string, mixed>
     */
    public function resolve(?User $user, array $browserContext): array
    {
        $page = is_array($browserContext['page'] ?? null)
            ? $browserContext['page']
            : [];

        $routeName = $this->cleanToken($page['route_name'] ?? null, 120);
        $module = $this->cleanToken($page['module'] ?? null, 60);
        $resourceType = $this->cleanToken($page['resource_type'] ?? null, 60);
        $resourceId = $this->positiveInt($page['resource_id'] ?? null);

        $resolved = [
            'route_name' => $routeName,
            'module' => $module,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource' => null,
        ];

        if (!$user || !$resourceId) {
            return $resolved;
        }

        if ($this->looksLikeProjectContext($routeName, $resourceType)) {
            $resolved['resource_type'] = 'najm_bahar_project';
            $resolved['resource'] = $this->resolveProject($user, $resourceId);
            return $resolved;
        }

        if ($this->looksLikeGroupContext($module, $resourceType)) {
            $resolved['resource_type'] = 'group';
            $resolved['resource'] = $this->resolveGroup($user, $resourceId);
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveProject(User $user, int $projectId): ?array
    {
        $project = Project::query()
            ->select([
                'id',
                'owner_type',
                'owner_id',
                'project_type',
                'project_visibility',
                'project_stage',
                'investment_method',
                'status',
                'risk_level',
                'target_market',
            ])
            ->find($projectId);

        if (!$project || !Gate::forUser($user)->allows('view', $project)) {
            return null;
        }

        $viewerRelation = 'authorized';
        if ($project->owner_type === User::class && (int) $project->owner_id === (int) $user->id) {
            $viewerRelation = 'owner';
        } elseif ($project->status === 'approved' && $project->project_visibility === 'public') {
            $viewerRelation = 'public';
        }

        return [
            'type' => 'najm_bahar_project',
            'id' => (int) $project->id,
            'project_type' => $this->cleanToken($project->project_type, 40),
            'project_visibility' => $this->cleanToken($project->project_visibility, 20),
            'project_stage' => $this->cleanToken($project->project_stage, 30),
            'investment_method' => $this->cleanToken($project->investment_method, 40),
            'status' => $this->cleanToken($project->status, 30),
            'risk_level' => $this->cleanToken($project->risk_level, 20),
            'target_market' => $this->cleanToken($project->target_market, 30),
            'viewer_relation' => $viewerRelation,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveGroup(User $user, int $groupId): ?array
    {
        $group = Group::query()
            ->select(['id', 'group_type', 'location_level', 'is_open'])
            ->find($groupId);

        if (!$group) {
            return null;
        }

        $isAdmin = (bool) ($user->is_admin ?? false) || $user->hasRole('super-admin');
        $membership = null;

        if (!$isAdmin) {
            $membership = GroupUser::query()
                ->where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->where('status', 1)
                ->first(['role']);

            if (!(bool) $group->is_open && !$membership) {
                return null;
            }
        }

        return [
            'type' => 'group',
            'id' => (int) $group->id,
            'group_type' => $this->cleanToken($group->group_type, 80),
            'location_level' => $this->cleanToken($group->location_level, 80),
            'is_open' => (bool) $group->is_open,
            'viewer_relation' => $isAdmin
                ? 'admin'
                : ($membership ? 'member' : 'public'),
            'viewer_group_role' => $membership && is_scalar($membership->role)
                ? mb_substr((string) $membership->role, 0, 20)
                : null,
        ];
    }

    protected function looksLikeProjectContext(?string $routeName, ?string $resourceType): bool
    {
        if (in_array($resourceType, ['project', 'najm_bahar_project'], true)) {
            return true;
        }

        return is_string($routeName) && Str::startsWith($routeName, 'najm-bahar.projects.');
    }

    protected function looksLikeGroupContext(?string $module, ?string $resourceType): bool
    {
        if ($resourceType === 'group') {
            return true;
        }

        return in_array(Str::lower((string) $module), ['group', 'groups'], true);
    }

    protected function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $number = (int) $value;
            return $number > 0 ? $number : null;
        }

        return null;
    }

    protected function cleanToken(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $value)) {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
