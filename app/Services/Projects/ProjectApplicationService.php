<?php

namespace App\Services\Projects;

use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Services\ProjectService;
use App\Services\Actors\ActorOperation;
use App\Services\Actors\ActorReference;
use App\Services\Actors\ActorRepresentationAuthorizationService;
use App\Services\Actors\ActorResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProjectApplicationService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ProjectScopeApplicationService $scope,
        private readonly ActorResolver $actors,
        private readonly ActorRepresentationAuthorizationService $representation,
    ) {}

    public function createForUser(User $user, array $data): Project
    {
        $data = $this->scope->normalize($data);

        return DB::transaction(function () use ($user, $data): Project {
            $project = $this->projects->createProject($user, $data);
            $project->forceFill([
                'target_location_id' => $data['target_location_id'] ?? null,
                'governance_area_id' => $data['governance_area_id'] ?? null,
            ])->save();

            return $project->fresh();
        });
    }

    public function update(Project $project, array $data): Project
    {
        $data = $this->scope->normalize($data);

        return DB::transaction(function () use ($project, $data): Project {
            $updated = $this->projects->updateProject($project, $data);
            $updated->forceFill([
                'target_location_id' => $data['target_location_id'] ?? null,
                'governance_area_id' => $data['governance_area_id'] ?? null,
            ])->save();

            return $updated->fresh();
        });
    }

    public function submit(Project $project): Project
    {
        return $this->projects->submitForReview($project);
    }

    public function ownerReference(Project $project): ActorReference
    {
        return $this->actors->fromLegacyOwner(
            (string) $project->owner_type,
            $project->owner_id,
        );
    }

    public function ownedQueryFor(User $principal, ActorReference $owner): Builder
    {
        $this->representation->authorize($principal, $owner, ActorOperation::ProjectOwner);
        $ownerModel = $this->actors->resolveModel($owner);

        return Project::query()
            ->where('owner_type', $ownerModel::class)
            ->where('owner_id', $ownerModel->getKey());
    }

    public function serialize(Project $project): array
    {
        return [
            'id' => (int) $project->id,
            'title' => (string) $project->title,
            'status' => (string) $project->status,
            'project_type' => (string) $project->project_type,
            'project_visibility' => (string) $project->project_visibility,
            'project_stage' => (string) $project->project_stage,
            'investment_method' => (string) $project->investment_method,
            'owner_actor' => $this->ownerReference($project)->toArray(),
            'category_level1_id' => $project->category_level1_id ? (int) $project->category_level1_id : null,
            'category_level2_id' => $project->category_level2_id ? (int) $project->category_level2_id : null,
            'category_level3_id' => $project->category_level3_id ? (int) $project->category_level3_id : null,
            'target_location_id' => $project->target_location_id ? (int) $project->target_location_id : null,
            'governance_area_id' => $project->governance_area_id ? (int) $project->governance_area_id : null,
            'summary' => $project->summary,
            'description' => $project->description,
            'created_at' => $project->created_at?->toISOString(),
            'updated_at' => $project->updated_at?->toISOString(),
            'submitted_at' => $project->submitted_at?->toISOString(),
        ];
    }
}
