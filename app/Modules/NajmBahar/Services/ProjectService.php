<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\User;
use App\Models\Group;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Models\ProjectReview;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use App\Notifications\NajmBahar\ProjectStatusChanged;
use App\Notifications\NajmBahar\ProjectRevisionRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class ProjectService
{
    /**
     * ایجاد پروژه جدید
     *
     * @param User|Group $owner صاحب پروژه
     * @param array $data اطلاعات پروژه
     * @return Project
     */
    public function createProject($owner, array $data): Project
    {
        $context = [
            'owner_type' => get_class($owner),
            'owner_id' => (int) ($owner->id ?? 0),
            'title' => (string) ($data['title'] ?? ''),
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.create.requested', $context);

        try {
            $result = DB::transaction(function () use ($owner, $data) {
                // اعتبارسنجی دسته‌بندی‌ها
                $this->validateCategoryHierarchy(
                    $data['category_level1_id'],
                    $data['category_level2_id'] ?? null,
                    $data['category_level3_id'] ?? null
                );

            // ساخت پروژه با وضعیت draft
            $investmentMethod = $data['investment_method'] ?? 'capital_participation';

            $project = new Project([
                'title' => $data['title'],
                'category_level1_id' => $data['category_level1_id'],
                'category_level2_id' => $data['category_level2_id'] ?? null,
                'category_level3_id' => $data['category_level3_id'] ?? null,
                'project_type' => $data['project_type'] ?? 'production',
                'project_visibility' => $data['project_visibility'] ?? 'public',
                'project_stage' => $data['project_stage'] ?? 'idea',
                'investment_method' => $investmentMethod,
                'existing_assets' => $data['existing_assets'] ?? null,
                'summary' => $data['summary'] ?? null,
                'description' => $data['description'] ?? null,
                'problem_statement' => $data['problem_statement'] ?? null,
                'solution_description' => $data['solution_description'] ?? null,
                'value_proposition' => $data['value_proposition'] ?? null,
                'target_market' => $data['target_market'] ?? null,
                'base_value_min' => $investmentMethod === 'auction_shares' ? ($data['base_value_min'] ?? null) : null,
                'base_value_max' => $investmentMethod === 'auction_shares' ? ($data['base_value_max'] ?? null) : null,
                'required_capital' => $investmentMethod === 'capital_participation' ? ($data['required_capital'] ?? null) : null,
                'profit_percentage' => $investmentMethod === 'capital_participation' ? ($data['profit_percentage'] ?? null) : null,
                'investment_duration_months' => $investmentMethod === 'capital_participation' ? ($data['investment_duration_months'] ?? null) : null,
                'total_shares' => $investmentMethod === 'auction_shares' ? ($data['total_shares'] ?? 100) : null,
                'initial_auction_percent' => $investmentMethod === 'auction_shares' ? ($data['initial_auction_percent'] ?? 10) : null,
                'max_user_ownership_percent' => $investmentMethod === 'auction_shares' ? ($data['max_user_ownership_percent'] ?? null) : null,
                'auction_period' => $investmentMethod === 'auction_shares' ? ($data['auction_period'] ?? null) : null,
                'risk_level' => $data['risk_level'] ?? null,
                'main_risks' => $data['main_risks'] ?? [],
                'oversight_type' => $data['oversight_type'] ?? null,
                'reporting_interval' => $data['reporting_interval'] ?? null,
                'fund_usage_scope' => $data['fund_usage_scope'] ?? 'project_only',
                'accept_transparency' => $data['accept_transparency'] ?? false,
                'failure_policy' => $data['failure_policy'] ?? null,
                'value_update_trigger' => $data['value_update_trigger'] ?? null,
                'accept_rules' => $data['accept_rules'] ?? false,
                'attachments' => $data['attachments'] ?? [],
                'status' => 'draft',
            ]);

            $project->owner()->associate($owner);
            $project->save();

            // ثبت تاریخچه
            $this->createReview($project, null, 'submitted', 'پروژه ایجاد شد');

                return $project->fresh();
            });

            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.create.succeeded', array_merge($context, [
                'project_id' => (int) ($result->id ?? 0),
                'status' => (string) ($result->status ?? ''),
            ]));
            return $result;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.create.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    /**
     * ارسال پروژه برای بررسی
     *
     * @param Project $project
     * @return Project
     */
    public function submitForReview(Project $project): Project
    {
        $context = [
            'project_id' => (int) $project->id,
            'current_status' => (string) ($project->status ?? ''),
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.submit_for_review.requested', $context);

        if ($project->status !== 'draft' && $project->status !== 'rejected') {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.submit_for_review.rejected', array_merge($context, [
                'reason' => 'invalid_project_status',
            ]));
            throw new \Exception('فقط پروژه‌های پیش‌نویس یا رد شده قابل ارسال مجدد هستند.');
        }

        try {
            $result = DB::transaction(function () use ($project) {
                // اعتبارسنجی اطلاعات
                $this->validateProjectData($project);

            $project->status = 'pending';
            $project->submitted_at = now();
            $project->save();

            // ثبت تاریخچه
            $action = $project->wasChanged('submitted_at') && $project->submitted_at->gt($project->created_at->addMinutes(5)) 
                ? 'resubmitted' 
                : 'submitted';

            $this->createReview($project, null, $action, 'پروژه برای بررسی ارسال شد');

                return $project->fresh();
            });

            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.submit_for_review.succeeded', array_merge($context, [
                'new_status' => (string) ($result->status ?? ''),
            ]));
            return $result;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.submit_for_review.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    /**
     * شروع بررسی پروژه توسط ادمین
     *
     * @param Project $project
     * @param User $reviewer
     * @return Project
     */
    public function startReview(Project $project, User $reviewer): Project
    {
        if ($project->status !== 'pending') {
            throw new \Exception('فقط پروژه‌های در انتظار بررسی قابل بررسی هستند.');
        }

        return DB::transaction(function () use ($project, $reviewer) {
            $project->status = 'under_review';
            $project->reviewed_at = now();
            $project->save();

            $this->createReview(
                $project, 
                $reviewer, 
                'under_review', 
                'بررسی پروژه آغاز شد'
            );

            return $project->fresh();
        });
    }

    /**
     * تایید پروژه توسط ادمین
     *
     * @param Project $project
     * @param User $reviewer
     * @param string|null $comment
     * @return Project
     */
    public function approveProject(Project $project, User $reviewer, ?string $comment = null): Project
    {
        $context = [
            'project_id' => (int) $project->id,
            'reviewer_id' => (int) $reviewer->id,
            'current_status' => (string) ($project->status ?? ''),
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.approve.requested', $context);

        if (!in_array($project->status, ['pending', 'under_review'])) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.approve.rejected', array_merge($context, [
                'reason' => 'invalid_project_status',
            ]));
            throw new \Exception('وضعیت پروژه برای تایید مناسب نیست.');
        }

        try {
            $result = DB::transaction(function () use ($project, $reviewer, $comment) {
                $project->status = 'approved';
                $project->approved_at = now();
                $project->admin_notes = $comment;
                $project->save();

            $this->createReview(
                $project, 
                $reviewer, 
                'approved', 
                $comment ?? 'پروژه تایید شد'
            );

            // ارسال اعلان به صاحب پروژه
            $project->owner->notify(new ProjectStatusChanged($project, 'approved', $comment));

                return $project->fresh();
            });

            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.approve.succeeded', array_merge($context, [
                'new_status' => (string) ($result->status ?? ''),
            ]));
            return $result;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.approve.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    /**
     * رد پروژه توسط ادمین
     *
     * @param Project $project
     * @param User $reviewer
     * @param string $reason
     * @param string|null $comment
     * @return Project
     */
    public function rejectProject(Project $project, User $reviewer, string $reason, ?string $comment = null): Project
    {
        $context = [
            'project_id' => (int) $project->id,
            'reviewer_id' => (int) $reviewer->id,
            'current_status' => (string) ($project->status ?? ''),
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.reject.requested', array_merge($context, [
            'reason_text' => $reason,
        ]));

        if (!in_array($project->status, ['pending', 'under_review'])) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.reject.rejected', array_merge($context, [
                'reason' => 'invalid_project_status',
            ]));
            throw new \Exception('وضعیت پروژه برای رد مناسب نیست.');
        }

        try {
            $result = DB::transaction(function () use ($project, $reviewer, $reason, $comment) {
                $project->status = 'rejected';
                $project->rejection_reason = $reason;
                $project->admin_notes = $comment;
                $project->save();

            $this->createReview(
                $project, 
                $reviewer, 
                'rejected', 
                $comment ?? $reason
            );

            // ارسال اعلان به صاحب پروژه با دلیل رد
            $project->owner->notify(new ProjectStatusChanged($project, 'rejected', $comment ?? $reason));

                return $project->fresh();
            });

            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.reject.succeeded', array_merge($context, [
                'new_status' => (string) ($result->status ?? ''),
            ]));
            return $result;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.reject.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    /**
     * درخواست اصلاح پروژه
     *
     * @param Project $project
     * @param User $reviewer
     * @param string $revisionNotes
     * @return Project
     */
    public function requestRevision(Project $project, User $reviewer, string $revisionNotes): Project
    {
        if (!in_array($project->status, ['pending', 'under_review'])) {
            throw new \Exception('وضعیت پروژه برای درخواست اصلاح مناسب نیست.');
        }

        return DB::transaction(function () use ($project, $reviewer, $revisionNotes) {
            $project->status = 'rejected'; // برگشت به rejected برای اصلاح
            $project->rejection_reason = $revisionNotes;
            $project->admin_notes = 'نیاز به اصلاح و ارسال مجدد';
            $project->save();

            $this->createReview(
                $project, 
                $reviewer, 
                'revision_requested', 
                $revisionNotes
            );

            // ارسال اعلان به صاحب پروژه
            $project->owner->notify(new ProjectRevisionRequested($project, $revisionNotes));

            return $project->fresh();
        });
    }

    /**
     * بایگانی پروژه
     *
     * @param Project $project
     * @param User|null $admin
     * @param string|null $reason
     * @return Project
     */
    public function archiveProject(Project $project, ?User $admin = null, ?string $reason = null): Project
    {
        return DB::transaction(function () use ($project, $admin, $reason) {
            $project->status = 'archived';
            $project->archived_at = now();
            $project->admin_notes = $reason;
            $project->save();

            $this->createReview(
                $project, 
                $admin, 
                'archived', 
                $reason ?? 'پروژه بایگانی شد'
            );

            return $project->fresh();
        });
    }

    /**
     * دریافت پروژه‌های مالک (کاربر یا گروه)
     *
     * @param User|Group $owner
     * @param array $statuses
     * @return Collection
     */
    public function getProjectsByOwner($owner, array $statuses = []): Collection
    {
        $query = $owner->najmBaharProjects();

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        return $query->with(['categoryLevel1', 'categoryLevel2', 'categoryLevel3', 'investments'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * دریافت پروژه‌های در انتظار بررسی
     *
     * @return Collection
     */
    public function getPendingProjects(): Collection
    {
        return Project::where('status', 'pending')
            ->with(['owner', 'categoryLevel1', 'categoryLevel2', 'categoryLevel3'])
            ->orderBy('submitted_at', 'asc')
            ->get();
    }

    /**
     * دریافت پروژه‌های تایید شده (برای فهرست سرمایه‌گذاری)
     *
     * @param array $filters
     * @return Collection
     */
    public function getApprovedProjects(array $filters = []): Collection
    {
        $query = Project::approved()
            ->with(['owner', 'categoryLevel1', 'categoryLevel2', 'categoryLevel3', 'investments']);

        // فیلتر بر اساس دسته‌بندی
        if (!empty($filters['category_level1_id'])) {
            $query->where('category_level1_id', $filters['category_level1_id']);
        }
        if (!empty($filters['category_level2_id'])) {
            $query->where('category_level2_id', $filters['category_level2_id']);
        }
        if (!empty($filters['category_level3_id'])) {
            $query->where('category_level3_id', $filters['category_level3_id']);
        }

        // فیلتر نوع پروژه
        if (!empty($filters['project_type'])) {
            $query->where('project_type', $filters['project_type']);
        }

        // مرتب‌سازی
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->get();
    }

    /**
     * دریافت آماربندی پروژه‌ها
     *
     * @param User|Group|null $owner
     * @return array
     */
    public function getProjectStatistics($owner = null): array
    {
        $query = Project::query();

        if ($owner) {
            $query->where('owner_type', get_class($owner))
                ->where('owner_id', $owner->id);
        }

        return [
            'total' => $query->count(),
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'under_review' => (clone $query)->where('status', 'under_review')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'archived' => (clone $query)->where('status', 'archived')->count(),
        ];
    }

    /**
     * اعتبارسنجی سلسله مراتب دسته‌بندی
     *
     * @param int $level1Id
     * @param int|null $level2Id
     * @param int|null $level3Id
     * @throws \Exception
     */
    protected function validateCategoryHierarchy(int $level1Id, ?int $level2Id, ?int $level3Id): void
    {
        // سطح 1 باید وجود داشته باشد
        $level1 = ProjectCategory::find($level1Id);
        if (!$level1 || $level1->level !== 1) {
            throw new \Exception('دسته‌بندی سطح اول نامعتبر است.');
        }

        // اگر سطح 2 مشخص شده، باید فرزند سطح 1 باشد
        if ($level2Id) {
            $level2 = ProjectCategory::find($level2Id);
            if (!$level2 || $level2->level !== 2 || $level2->parent_id !== $level1Id) {
                throw new \Exception('دسته‌بندی سطح دوم نامعتبر است.');
            }

            // اگر سطح 3 مشخص شده، باید فرزند سطح 2 باشد
            if ($level3Id) {
                $level3 = ProjectCategory::find($level3Id);
                if (!$level3 || $level3->level !== 3 || $level3->parent_id !== $level2Id) {
                    throw new \Exception('دسته‌بندی سطح سوم نامعتبر است.');
                }
            }
        }
    }

    /**
     * اعتبارسنجی اطلاعات پروژه
     *
     * @param Project $project
     * @throws \Exception
     */
    protected function validateProjectData(Project $project): void
    {
        if (empty($project->title)) {
            throw new \Exception('عنوان پروژه الزامی است.');
        }

        if (empty($project->problem_statement)) {
            throw new \Exception('شرح مسئله پروژه الزامی است.');
        }

        if (empty($project->solution_description)) {
            throw new \Exception('شرح راه‌حل پروژه الزامی است.');
        }

        if ($project->investment_method === 'auction_shares') {
            if (!$project->base_value_min || !$project->base_value_max) {
                throw new \Exception('بازه ارزش پایه پروژه الزامی است.');
            }

            if ($project->base_value_max < $project->base_value_min) {
                throw new \Exception('حداکثر ارزش پایه باید بزرگ‌تر یا مساوی حداقل باشد.');
            }
        }

        if ($project->investment_method === 'capital_participation') {
            if (!$project->required_capital || $project->required_capital <= 0) {
                throw new \Exception('مبلغ سرمایه باید بیشتر از صفر باشد.');
            }

            if (!$project->profit_percentage || $project->profit_percentage <= 0 || $project->profit_percentage > 100) {
                throw new \Exception('درصد سود باید بین 0 تا 100 باشد.');
            }

            if (!$project->investment_duration_months || $project->investment_duration_months <= 0) {
                throw new \Exception('مدت سرمایه‌گذاری الزامی است.');
            }
        }

        if (!$project->category_level1_id) {
            throw new \Exception('دسته‌بندی پروژه الزامی است.');
        }

        if (!$project->accept_transparency) {
            throw new \Exception('پذیرش شفافیت مالی الزامی است.');
        }

        if (!$project->accept_rules) {
            throw new \Exception('پذیرش قوانین نجم بهار الزامی است.');
        }
    }

    /**
     * ثبت تاریخچه بررسی
     *
     * @param Project $project
     * @param User|null $reviewer
     * @param string $action
     * @param string|null $comment
     * @return ProjectReview
     */
    protected function createReview(
        Project $project, 
        ?User $reviewer, 
        string $action, 
        ?string $comment = null
    ): ProjectReview {
        return ProjectReview::create([
            'project_id' => $project->id,
            'reviewer_id' => $reviewer?->id,
            'action' => $action,
            'comment' => $comment,
            'metadata' => [
                'previous_status' => $project->getOriginal('status'),
                'new_status' => $project->status,
            ],
        ]);
    }

    /**
     * ویرایش پروژه
     *
     * @param Project $project
     * @param array $data
     * @return Project
     */
    public function updateProject(Project $project, array $data): Project
    {
        // فقط پروژه‌های draft یا rejected قابل ویرایش هستند
        if (!in_array($project->status, ['draft', 'rejected'])) {
            throw new \Exception('فقط پروژه‌های پیش‌نویس یا رد شده قابل ویرایش هستند.');
        }

        return DB::transaction(function () use ($project, $data) {
            // اعتبارسنجی دسته‌بندی در صورت تغییر
            if (isset($data['category_level1_id'])) {
                $this->validateCategoryHierarchy(
                    $data['category_level1_id'],
                    $data['category_level2_id'] ?? null,
                    $data['category_level3_id'] ?? null
                );
            }

            $project->fill($data);
            $project->save();

            return $project->fresh();
        });
    }

    /**
     * ارجاع پروژه برای بررسی توسط کاربر یا گروه تخصص
     *
     * @param Project $project
     * @param string $type نوع مقصد: User یا Group
     * @param int $targetId شناسه User یا Group
     * @param string|null $note نظر ارجاع دهنده
     * @return Project
     */
    public function assignProjectToReviewer(Project $project, string $type, int $targetId, ?string $note = null): Project
    {
        $context = [
            'project_id' => (int) $project->id,
            'target_type' => $type,
            'target_id' => $targetId,
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.assign.requested', $context);

        try {
            $result = DB::transaction(function () use ($project, $type, $targetId, $note) {
            // بررسی نوع معتبر
            if (!in_array($type, ['User', 'Group'])) {
                throw new \Exception('نوع مقصد باید User یا Group باشد.');
            }

            // بررسی وجود مقصد
            $modelClass = 'App\Models\\' . $type;
            if (!class_exists($modelClass)) {
                throw new \Exception('مدل ' . $type . ' یافت نشد.');
            }

            $target = $modelClass::find($targetId);
            if (!$target) {
                throw new \Exception($type . ' با شناسه ' . $targetId . ' یافت نشد.');
            }

            // ترجیح: تنها می‌توان پروژه‌های pending یا under_review را ارجاع داد
            if (!in_array($project->status, ['pending', 'under_review'])) {
                throw new \Exception('فقط پروژه‌های در انتظار بررسی یا تحت بررسی قابل ارجاع هستند.');
            }

            // بروزرسانی ارجاع
            $project->assigned_to_type = $type;
            $project->assigned_to_id = $targetId;
            $project->assigned_at = now();
            $project->assignment_note = $note;
            $project->assignment_status = 'pending';
            $project->save();

            // ثبت تاریخچه
            $this->createReview(
                $project,
                Auth::user(),
                'assigned',
                'پروژه برای بررسی به ' . ($type === 'User' ? $target->name : $target->name) . ' ارجاع شد.' . ($note ? ' - ' . $note : '')
            );

            // ارسال اطلاع‌رسانی به مقصد (User یا تمام اعضای Group)
            if ($type === 'User') {
                // اطلاع‌رسانی مستقیم به کاربر
                $target->notify(new \App\Notifications\NajmBahar\ProjectAssigned($project));
            } else {
                // اطلاع‌رسانی به تمام اعضای گروه
                $groupMembers = $target->users()->get();
                foreach ($groupMembers as $member) {
                    $member->notify(new \App\Notifications\NajmBahar\ProjectAssigned($project));
                }
            }

                return $project->fresh();
            });

            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.assign.succeeded', array_merge($context, [
                'assignment_status' => (string) ($result->assignment_status ?? ''),
            ]));
            return $result;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.assign.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    /**
     * بروزرسانی نتیجه بررسی ارجاع شده
     *
     * @param Project $project
     * @param string $status نتیجه: completed یا rejected
     * @param string|null $reviewNote نظر بررسی کننده
     * @return Project
     */
    public function updateAssignmentReview(Project $project, string $status, ?string $reviewNote = null): Project
    {
        $context = [
            'project_id' => (int) $project->id,
            'assignment_status' => $status,
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.assignment_review.requested', $context);

        if (!in_array($status, ['completed', 'rejected'])) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.assignment_review.rejected', array_merge($context, [
                'reason' => 'invalid_assignment_status',
            ]));
            throw new \Exception('وضعیت باید completed یا rejected باشد.');
        }

        try {
            $result = DB::transaction(function () use ($project, $status, $reviewNote) {
                $project->assignment_status = $status;
                $project->assignment_review_note = $reviewNote;
                $project->assignment_completed_at = now();
                $project->save();

            // ثبت تاریخچه
            $statusLabel = $status === 'completed' ? 'تکمیل شده' : 'رد شده';
            $this->createReview(
                $project,
                Auth::user(),
                'assignment_' . $status,
                'بررسی ارجاع شده: ' . $statusLabel . ($reviewNote ? ' - ' . $reviewNote : '')
            );

                return $project->fresh();
            });

            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.assignment_review.succeeded', array_merge($context, [
                'assignment_status' => (string) ($result->assignment_status ?? ''),
            ]));
            return $result;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.project.assignment_review.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
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
            // Telemetry must not break project workflows.
        }
    }
}
