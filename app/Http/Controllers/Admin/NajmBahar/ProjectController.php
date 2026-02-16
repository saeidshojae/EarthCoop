<?php

namespace App\Http\Controllers\Admin\NajmBahar;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Group;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    protected ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->middleware(['auth', 'admin']);
        $this->projectService = $projectService;
    }

    /**
     * نمایش لیست پروژه‌های در انتظار بررسی
     */
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        
        $query = Project::query()
            ->with(['owner', 'categoryLevel1', 'categoryLevel2', 'categoryLevel3']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $projects = $query->orderBy('submitted_at', 'asc')->paginate(20);
        $stats = $this->projectService->getProjectStatistics();

        return view('admin.najm-bahar.projects.index', compact('projects', 'stats', 'status'));
    }

    /**
     * نمایش جزئیات پروژه برای بررسی
     */
    public function show(Project $project)
    {
        $project->load([
            'owner',
            'categoryLevel1',
            'categoryLevel2',
            'categoryLevel3',
            'reviews.reviewer',
            'investments'
        ]);

        return view('admin.najm-bahar.projects.show', compact('project'));
    }

    /**
     * شروع بررسی پروژه
     */
    public function startReview(Project $project)
    {
        try {
            $admin = Auth::user();
            $this->projectService->startReview($project, $admin);

            return redirect()
                ->route('admin.najm-bahar.projects.show', $project)
                ->with('success', 'بررسی پروژه آغاز شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * تایید پروژه
     */
    public function approve(Request $request, Project $project)
    {
        $validated = $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $admin = Auth::user();
            $this->projectService->approveProject(
                $project,
                $admin,
                $validated['comment'] ?? null
            );

            return redirect()
                ->route('admin.najm-bahar.projects.index')
                ->with('success', 'پروژه با موفقیت تایید شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * رد پروژه
     */
    public function reject(Request $request, Project $project)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $admin = Auth::user();
            $this->projectService->rejectProject(
                $project,
                $admin,
                $validated['reason'],
                $validated['comment'] ?? null
            );

            return redirect()
                ->route('admin.najm-bahar.projects.index')
                ->with('success', 'پروژه رد شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * درخواست اصلاح پروژه
     */
    public function requestRevision(Request $request, Project $project)
    {
        $validated = $request->validate([
            'revision_notes' => 'required|string|max:1000',
        ]);

        try {
            $admin = Auth::user();
            $this->projectService->requestRevision(
                $project,
                $admin,
                $validated['revision_notes']
            );

            return redirect()
                ->route('admin.najm-bahar.projects.index')
                ->with('success', 'درخواست اصلاح برای صاحب پروژه ارسال شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * بایگانی پروژه
     */
    public function archive(Request $request, Project $project)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $admin = Auth::user();
            $this->projectService->archiveProject(
                $project,
                $admin,
                $validated['reason'] ?? null
            );

            return redirect()
                ->route('admin.najm-bahar.projects.index')
                ->with('success', 'پروژه بایگانی شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * ارجاع پروژه برای بررسی توسط کاربر یا گروه تخصص
     */
    public function assign(Request $request, Project $project)
    {
        $validated = $request->validate([
            'assigned_to_type' => 'required|in:User,Group',
            'assigned_to_id' => 'required|integer|min:1',
            'assignment_note' => 'nullable|string|max:500',
        ]);

        try {
            $this->projectService->assignProjectToReviewer(
                $project,
                $validated['assigned_to_type'],
                $validated['assigned_to_id'],
                $validated['assignment_note'] ?? null
            );

            return redirect()
                ->route('admin.najm-bahar.projects.show', $project)
                ->with('success', 'پروژه با موفقیت ارجاع شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * بروزرسانی نتیجه بررسی ارجاع شده
     */
    public function updateAssignmentReview(Request $request, Project $project)
    {
        $validated = $request->validate([
            'assignment_status' => 'required|in:completed,rejected',
            'assignment_review_note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->projectService->updateAssignmentReview(
                $project,
                $validated['assignment_status'],
                $validated['assignment_review_note'] ?? null
            );

            return redirect()
                ->route('admin.najm-bahar.projects.show', $project)
                ->with('success', 'نتیجه بررسی ارجاع شده بروزرسانی شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * دریافت لیست کاربران برای ارجاع
     */
    public function getUsers()
    {
        $users = User::query()
            ->where('is_admin', true)
            ->orWhere('is_specialist', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name
            ]);

        return response()->json([
            'success' => true,
            'items' => $users
        ]);
    }

    /**
     * دریافت لیست گروه‌ها برای ارجاع
     */
    public function getGroups()
    {
        $groups = Group::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name
            ]);

        return response()->json([
            'success' => true,
            'items' => $groups
        ]);
    }
}
