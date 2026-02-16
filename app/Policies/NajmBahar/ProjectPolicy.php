<?php

namespace App\Policies\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    /**
     * مشاهده پروژه
     */
    public function view(User $user, Project $project): bool
    {
        // صاحب پروژه می‌تواند ببیند
        if ($project->owner_type === User::class && $project->owner_id === $user->id) {
            return true;
        }

        // پروژه‌های تایید شده عمومی قابل مشاهده هستند
        if ($project->status === 'approved' && $project->project_type === 'public') {
            return true;
        }

        // ادمین‌ها می‌توانند همه را ببینند
        if ($user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * ویرایش پروژه
     */
    public function update(User $user, Project $project): bool
    {
        // فقط صاحب پروژه می‌تواند ویرایش کند
        if ($project->owner_type === User::class && $project->owner_id === $user->id) {
            // فقط پروژه‌های draft یا rejected قابل ویرایش هستند
            return in_array($project->status, ['draft', 'rejected']);
        }

        return false;
    }

    /**
     * حذف پروژه
     */
    public function delete(User $user, Project $project): bool
    {
        // فقط صاحب پروژه می‌تواند حذف کند
        if ($project->owner_type === User::class && $project->owner_id === $user->id) {
            // فقط پروژه‌های draft قابل حذف هستند
            return $project->status === 'draft';
        }

        return false;
    }
}
