<?php

namespace App\Http\Controllers;

use App\Helpers\BaharMoney;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmBaharProject;
use App\Models\OccupationalField;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class NajmBaharProjectController extends Controller
{
    public function index(AccountService $accountService)
    {
        $account = $this->ensureUserAccount($accountService);
        if (! $account) {
            return $this->redirectToAgreement();
        }

        $projects = NajmBaharProject::with(['category.parent.parent'])
            ->where('user_id', $account->user_id)
            ->latest()
            ->paginate(10);

        return view('najm-bahar.projects.index', [
            'account' => $account,
            'projects' => $projects,
            'routePrefix' => 'najm-bahar',
            'routeParams' => [],
            'ownerLabel' => 'شما',
        ]);
    }

    public function create(AccountService $accountService)
    {
        $account = $this->ensureUserAccount($accountService);
        if (! $account) {
            return $this->redirectToAgreement();
        }

        $level1Fields = OccupationalField::whereNull('parent_id')->orderBy('name')->get();

        return view('najm-bahar.projects.create', [
            'account' => $account,
            'level1Fields' => $level1Fields,
            'routePrefix' => 'najm-bahar',
            'routeParams' => [],
            'ownerLabel' => 'شما',
        ]);
    }

    public function store(Request $request, AccountService $accountService)
    {
        $account = $this->ensureUserAccount($accountService);
        if (! $account) {
            return $this->redirectToAgreement();
        }

        $validated = $this->validateProjectRequest($request, true);
        $this->validateCategoryHierarchy($validated);

        $fileData = $this->storePlanFile($request);

        NajmBaharProject::create([
            'user_id' => $account->user_id,
            'occupational_field_id' => (int) $validated['category_id'],
            'title' => $validated['title'],
            'project_type' => $validated['project_type'],
            'profit_percent' => $validated['profit_percent'],
            'summary' => $validated['summary'],
            'full_plan_path' => $fileData['path'],
            'full_plan_original_name' => $fileData['original_name'],
            'full_plan_mime' => $fileData['mime'],
            'full_plan_size' => $fileData['size'],
            'investment_amount' => BaharMoney::parseToGol($validated['investment_amount']),
            'duration_months' => (int) $validated['duration_months'],
            'status' => 'pending',
        ]);

        return redirect()->route('najm-bahar.projects.index')
            ->with('success', 'طرح توجیهی شما با موفقیت ثبت شد و در انتظار بررسی است.');
    }

    public function show(NajmBaharProject $project, AccountService $accountService)
    {
        $account = $this->ensureUserAccount($accountService);
        if (! $account) {
            return $this->redirectToAgreement();
        }

        $this->ensureProjectOwner($project, $account->user_id);

        $project->load(['category.parent.parent', 'investments.user']);

        return view('najm-bahar.projects.show', [
            'account' => $account,
            'project' => $project,
            'routePrefix' => 'najm-bahar',
            'routeParams' => [],
            'ownerLabel' => 'شما',
        ]);
    }

    public function edit(NajmBaharProject $project, AccountService $accountService)
    {
        $account = $this->ensureUserAccount($accountService);
        if (! $account) {
            return $this->redirectToAgreement();
        }

        $this->ensureProjectOwner($project, $account->user_id);

        if (! in_array($project->status, ['pending', 'rejected'], true)) {
            return redirect()->route('najm-bahar.projects.show', $project)
                ->with('error', 'فقط طرح‌های در انتظار یا رد شده قابل ویرایش هستند.');
        }

        $category = $project->category?->load('parent.parent');

        $level1Fields = OccupationalField::whereNull('parent_id')->orderBy('name')->get();

        return view('najm-bahar.projects.edit', [
            'account' => $account,
            'project' => $project,
            'level1Fields' => $level1Fields,
            'selectedLevel1' => $category?->parent?->parent?->id,
            'selectedLevel2' => $category?->parent?->id,
            'selectedLevel3' => $category?->id,
            'routePrefix' => 'najm-bahar',
            'routeParams' => [],
            'ownerLabel' => 'شما',
        ]);
    }

    public function update(Request $request, NajmBaharProject $project, AccountService $accountService)
    {
        $account = $this->ensureUserAccount($accountService);
        if (! $account) {
            return $this->redirectToAgreement();
        }

        $this->ensureProjectOwner($project, $account->user_id);

        if (! in_array($project->status, ['pending', 'rejected'], true)) {
            return redirect()->route('najm-bahar.projects.show', $project)
                ->with('error', 'فقط طرح‌های در انتظار یا رد شده قابل ویرایش هستند.');
        }

        $validated = $this->validateProjectRequest($request, false);
        $this->validateCategoryHierarchy($validated);

        $payload = [
            'occupational_field_id' => (int) $validated['category_id'],
            'title' => $validated['title'],
            'project_type' => $validated['project_type'],
            'profit_percent' => $validated['profit_percent'],
            'summary' => $validated['summary'],
            'investment_amount' => BaharMoney::parseToGol($validated['investment_amount']),
            'duration_months' => (int) $validated['duration_months'],
        ];

        if ($request->hasFile('full_plan_file')) {
            $fileData = $this->storePlanFile($request, $project->full_plan_path);
            $payload['full_plan_path'] = $fileData['path'];
            $payload['full_plan_original_name'] = $fileData['original_name'];
            $payload['full_plan_mime'] = $fileData['mime'];
            $payload['full_plan_size'] = $fileData['size'];
        }

        $project->update($payload);

        return redirect()->route('najm-bahar.projects.show', $project)
            ->with('success', 'اطلاعات طرح شما با موفقیت بروزرسانی شد.');
    }

    public function resubmit(NajmBaharProject $project, AccountService $accountService)
    {
        $account = $this->ensureUserAccount($accountService);
        if (! $account) {
            return $this->redirectToAgreement();
        }

        $this->ensureProjectOwner($project, $account->user_id);

        if ($project->status !== 'rejected') {
            return redirect()->route('najm-bahar.projects.show', $project)
                ->with('error', 'فقط طرح‌های رد شده امکان ارسال مجدد دارند.');
        }

        $project->update([
            'status' => 'pending',
            'rejection_reason' => null,
            'rejected_at' => null,
            'resubmitted_at' => now(),
        ]);

        return redirect()->route('najm-bahar.projects.show', $project)
            ->with('success', 'طرح شما دوباره برای بررسی ارسال شد.');
    }

    public function indexForGroup(Group $group, AccountService $accountService)
    {
        $account = $this->ensureGroupAccount($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به مدیریت طرح‌های گروه برای شما مجاز نیست.');
        }

        $projects = NajmBaharProject::with(['category.parent.parent'])
            ->where('group_id', $group->id)
            ->latest()
            ->paginate(10);

        return view('najm-bahar.projects.index', [
            'account' => $account,
            'group' => $group,
            'projects' => $projects,
            'routePrefix' => 'groups.najm-bahar',
            'routeParams' => ['group' => $group->id],
            'ownerLabel' => 'گروه ' . $group->name,
        ]);
    }

    public function createForGroup(Group $group, AccountService $accountService)
    {
        $account = $this->ensureGroupAccount($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به ثبت طرح برای این گروه مجاز نیست.');
        }

        $level1Fields = OccupationalField::whereNull('parent_id')->orderBy('name')->get();

        return view('najm-bahar.projects.create', [
            'account' => $account,
            'group' => $group,
            'level1Fields' => $level1Fields,
            'routePrefix' => 'groups.najm-bahar',
            'routeParams' => ['group' => $group->id],
            'ownerLabel' => 'گروه ' . $group->name,
        ]);
    }

    public function storeForGroup(Request $request, Group $group, AccountService $accountService)
    {
        $account = $this->ensureGroupAccount($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به ثبت طرح برای این گروه مجاز نیست.');
        }

        $validated = $this->validateProjectRequest($request, true);
        $this->validateCategoryHierarchy($validated);

        $fileData = $this->storePlanFile($request);

        NajmBaharProject::create([
            'group_id' => $group->id,
            'occupational_field_id' => (int) $validated['category_id'],
            'title' => $validated['title'],
            'project_type' => $validated['project_type'],
            'profit_percent' => $validated['profit_percent'],
            'summary' => $validated['summary'],
            'full_plan_path' => $fileData['path'],
            'full_plan_original_name' => $fileData['original_name'],
            'full_plan_mime' => $fileData['mime'],
            'full_plan_size' => $fileData['size'],
            'investment_amount' => BaharMoney::parseToGol($validated['investment_amount']),
            'duration_months' => (int) $validated['duration_months'],
            'status' => 'pending',
        ]);

        return redirect()->route('groups.najm-bahar.projects.index', $group)
            ->with('success', 'طرح توجیهی گروه با موفقیت ثبت شد و در انتظار بررسی است.');
    }

    public function showForGroup(Group $group, NajmBaharProject $project, AccountService $accountService)
    {
        $account = $this->ensureGroupAccount($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به مشاهده طرح‌های گروه برای شما مجاز نیست.');
        }

        if ((int) $project->group_id !== (int) $group->id) {
            return redirect()->route('groups.najm-bahar.projects.index', $group)
                ->with('error', 'این طرح متعلق به گروه شما نیست.');
        }

        $project->load(['category.parent.parent', 'investments.user']);

        return view('najm-bahar.projects.show', [
            'account' => $account,
            'group' => $group,
            'project' => $project,
            'routePrefix' => 'groups.najm-bahar',
            'routeParams' => ['group' => $group->id],
            'ownerLabel' => 'گروه ' . $group->name,
        ]);
    }

    public function editForGroup(Group $group, NajmBaharProject $project, AccountService $accountService)
    {
        $account = $this->ensureGroupAccount($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به ویرایش طرح‌های گروه برای شما مجاز نیست.');
        }

        if ((int) $project->group_id !== (int) $group->id) {
            return redirect()->route('groups.najm-bahar.projects.index', $group)
                ->with('error', 'این طرح متعلق به گروه شما نیست.');
        }

        if (! in_array($project->status, ['pending', 'rejected'], true)) {
            return redirect()->route('groups.najm-bahar.projects.show', [$group, $project])
                ->with('error', 'فقط طرح‌های در انتظار یا رد شده قابل ویرایش هستند.');
        }

        $category = $project->category?->load('parent.parent');
        $level1Fields = OccupationalField::whereNull('parent_id')->orderBy('name')->get();

        return view('najm-bahar.projects.edit', [
            'account' => $account,
            'group' => $group,
            'project' => $project,
            'level1Fields' => $level1Fields,
            'selectedLevel1' => $category?->parent?->parent?->id,
            'selectedLevel2' => $category?->parent?->id,
            'selectedLevel3' => $category?->id,
            'routePrefix' => 'groups.najm-bahar',
            'routeParams' => ['group' => $group->id],
            'ownerLabel' => 'گروه ' . $group->name,
        ]);
    }

    public function updateForGroup(Request $request, Group $group, NajmBaharProject $project, AccountService $accountService)
    {
        $account = $this->ensureGroupAccount($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به ویرایش طرح‌های گروه برای شما مجاز نیست.');
        }

        if ((int) $project->group_id !== (int) $group->id) {
            return redirect()->route('groups.najm-bahar.projects.index', $group)
                ->with('error', 'این طرح متعلق به گروه شما نیست.');
        }

        if (! in_array($project->status, ['pending', 'rejected'], true)) {
            return redirect()->route('groups.najm-bahar.projects.show', [$group, $project])
                ->with('error', 'فقط طرح‌های در انتظار یا رد شده قابل ویرایش هستند.');
        }

        $validated = $this->validateProjectRequest($request, false);
        $this->validateCategoryHierarchy($validated);

        $payload = [
            'occupational_field_id' => (int) $validated['category_id'],
            'title' => $validated['title'],
            'project_type' => $validated['project_type'],
            'profit_percent' => $validated['profit_percent'],
            'summary' => $validated['summary'],
            'investment_amount' => BaharMoney::parseToGol($validated['investment_amount']),
            'duration_months' => (int) $validated['duration_months'],
        ];

        if ($request->hasFile('full_plan_file')) {
            $fileData = $this->storePlanFile($request, $project->full_plan_path);
            $payload['full_plan_path'] = $fileData['path'];
            $payload['full_plan_original_name'] = $fileData['original_name'];
            $payload['full_plan_mime'] = $fileData['mime'];
            $payload['full_plan_size'] = $fileData['size'];
        }

        $project->update($payload);

        return redirect()->route('groups.najm-bahar.projects.show', [$group, $project])
            ->with('success', 'اطلاعات طرح گروه با موفقیت بروزرسانی شد.');
    }

    public function resubmitForGroup(Group $group, NajmBaharProject $project, AccountService $accountService)
    {
        $account = $this->ensureGroupAccount($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به طرح‌های گروه برای شما مجاز نیست.');
        }

        if ((int) $project->group_id !== (int) $group->id) {
            return redirect()->route('groups.najm-bahar.projects.index', $group)
                ->with('error', 'این طرح متعلق به گروه شما نیست.');
        }

        if ($project->status !== 'rejected') {
            return redirect()->route('groups.najm-bahar.projects.show', [$group, $project])
                ->with('error', 'فقط طرح‌های رد شده امکان ارسال مجدد دارند.');
        }

        $project->update([
            'status' => 'pending',
            'rejection_reason' => null,
            'rejected_at' => null,
            'resubmitted_at' => now(),
        ]);

        return redirect()->route('groups.najm-bahar.projects.show', [$group, $project])
            ->with('success', 'طرح گروه دوباره برای بررسی ارسال شد.');
    }

    private function ensureUserAccount(AccountService $accountService)
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        return $accountService->getMainAccountForUser($user->id);
    }

    private function ensureGroupAccount(Group $group, AccountService $accountService)
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $isManager = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->whereIn('role', [2, 3])
            ->where('status', 1)
            ->exists();

        if (! $isManager) {
            return null;
        }

        return $accountService->ensureLegalEntityAccountForGroup($group);
    }

    private function ensureProjectOwner(NajmBaharProject $project, int $userId): void
    {
        if ((int) $project->user_id !== $userId) {
            abort(403, 'دسترسی غیرمجاز');
        }
    }

    private function validateProjectRequest(Request $request, bool $requireFile): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'category_level1' => 'required|exists:occupational_fields,id',
            'category_level2' => 'required|exists:occupational_fields,id',
            'category_id' => 'required|exists:occupational_fields,id',
            'project_type' => 'required|in:public,private',
            'profit_percent' => 'required|numeric|min:0|max:100',
            'summary' => 'required|string|max:3000',
            'investment_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'duration_months' => 'required|integer|min:1|max:600',
            'full_plan_file' => ($requireFile ? 'required' : 'nullable') . '|file|mimes:pdf,doc,docx|max:10240',
        ], [
            'category_id.required' => 'لطفا دسته بندی سطح سوم را انتخاب کنید.',
            'investment_amount.regex' => 'مبلغ سرمایه باید به صورت عددی با حداکثر دو رقم اعشار باشد.',
        ]);
    }

    private function validateCategoryHierarchy(array $validated): void
    {
        $level1 = OccupationalField::find((int) $validated['category_level1']);
        $level2 = OccupationalField::find((int) $validated['category_level2']);
        $level3 = OccupationalField::find((int) $validated['category_id']);

        if (! $level1 || ! $level2 || ! $level3) {
            throw ValidationException::withMessages([
                'category_id' => 'دسته بندی انتخاب شده معتبر نیست.',
            ]);
        }

        if ((int) $level2->parent_id !== (int) $level1->id || (int) $level3->parent_id !== (int) $level2->id) {
            throw ValidationException::withMessages([
                'category_id' => 'ترتیب سطوح دسته بندی صحیح نیست.',
            ]);
        }
    }

    private function storePlanFile(Request $request, ?string $previousPath = null): array
    {
        $file = $request->file('full_plan_file');
        $path = $file->store('najm-bahar/projects', 'public');

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    private function redirectToAgreement()
    {
        return redirect()->route('najm-bahar.agreement')
            ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
    }
}
