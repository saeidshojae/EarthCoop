<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\Api\V1\Pagination;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Services\Projects\ProjectApplicationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectApplicationService $application)
    {
        $page = Pagination::page($request, 20, 50);
        $query = Project::query()
            ->where('owner_type', User::class)
            ->where('owner_id', $request->user()->id)
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $items = $query->forPage($page->number, $page->size)->get()
            ->map(fn (Project $project) => $application->serialize($project))->values()->all();

        return response()->json([
            'items' => $items,
            'page' => ['number' => $page->number, 'size' => $page->size, 'total' => $total],
        ]);
    }

    public function show(Request $request, Project $project, ProjectApplicationService $application)
    {
        $this->authorize('view', $project);

        return response()->json($application->serialize($project));
    }

    public function store(Request $request, ProjectApplicationService $application)
    {
        $validated = $request->validate($this->rules());
        $project = $application->createForUser($request->user(), $validated);

        return response()->json($application->serialize($project), 201);
    }

    public function update(Request $request, Project $project, ProjectApplicationService $application)
    {
        $this->authorize('update', $project);
        $validated = $request->validate($this->rules());
        $project = $application->update($project, $validated);

        return response()->json($application->serialize($project));
    }

    public function submit(Request $request, Project $project, ProjectApplicationService $application)
    {
        $this->authorize('update', $project);
        $project = $application->submit($project);

        return response()->json($application->serialize($project));
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_level1_id' => ['required', 'exists:najm_bahar_project_categories,id'],
            'category_level2_id' => ['nullable', 'exists:najm_bahar_project_categories,id'],
            'category_level3_id' => ['nullable', 'exists:najm_bahar_project_categories,id'],
            'project_type' => ['required', Rule::in(['production', 'service', 'infrastructure', 'research', 'social'])],
            'project_visibility' => ['required', Rule::in(['public', 'private'])],
            'project_stage' => ['required', Rule::in(['idea', 'documented', 'prototype', 'active'])],
            'summary' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'problem_statement' => ['required', 'string'],
            'solution_description' => ['required', 'string'],
            'value_proposition' => ['nullable', 'string'],
            'target_market' => ['required', Rule::in(['local', 'professional', 'general', 'external'])],
            'existing_assets' => ['nullable', 'string'],
            'investment_method' => ['required', Rule::in(['auction_shares', 'capital_participation'])],
            'base_value_min' => ['required_if:investment_method,auction_shares', 'nullable', 'integer', 'min:1'],
            'base_value_max' => ['required_if:investment_method,auction_shares', 'nullable', 'integer', 'min:1', 'gte:base_value_min'],
            'total_shares' => ['required_if:investment_method,auction_shares', 'nullable', 'integer', 'min:1'],
            'initial_auction_percent' => ['required_if:investment_method,auction_shares', 'nullable', 'numeric', 'min:0', 'max:100'],
            'max_user_ownership_percent' => ['required_if:investment_method,auction_shares', 'nullable', 'numeric', 'min:0', 'max:100'],
            'auction_period' => ['required_if:investment_method,auction_shares', 'nullable', Rule::in(['monthly', 'quarterly', 'semi_annual', 'annual'])],
            'required_capital' => ['required_if:investment_method,capital_participation', 'nullable', 'integer', 'min:1'],
            'profit_percentage' => ['required_if:investment_method,capital_participation', 'nullable', 'numeric', 'min:0.01', 'max:100'],
            'investment_duration_months' => ['required_if:investment_method,capital_participation', 'nullable', 'integer', 'min:1'],
            'risk_level' => ['required', Rule::in(['low', 'medium', 'high'])],
            'main_risks' => ['nullable', 'array'],
            'oversight_type' => ['required', Rule::in(['guild', 'insurance', 'both', 'none'])],
            'reporting_interval' => ['required', Rule::in(['monthly', 'quarterly', 'semi_annual', 'annual'])],
            'fund_usage_scope' => ['required', Rule::in(['project_only'])],
            'accept_transparency' => ['accepted'],
            'failure_policy' => ['required', Rule::in(['refund', 'asset_conversion', 'vote'])],
            'value_update_trigger' => ['required', Rule::in(['stage_progress', 'oversight_approval'])],
            'accept_rules' => ['accepted'],
            'target_location_id' => ['nullable', 'integer'],
            'governance_area_id' => ['nullable', 'integer'],
        ];
    }
}
