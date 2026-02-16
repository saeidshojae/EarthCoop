<?php

namespace App\Policies\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Investment;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvestmentPolicy
{
    use HandlesAuthorization;

    /**
     * مشاهده سرمایه‌گذاری
     */
    public function view(User $user, Investment $investment): bool
    {
        // سرمایه‌گذار می‌تواند ببیند
        if ($investment->investor_type === User::class && $investment->investor_id === $user->id) {
            return true;
        }

        // صاحب پروژه می‌تواند ببیند
        if ($investment->project->owner_type === User::class && $investment->project->owner_id === $user->id) {
            return true;
        }

        // ادمین‌ها می‌توانند همه را ببینند
        if ($user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * ویرایش/پرداخت سرمایه‌گذاری
     */
    public function update(User $user, Investment $investment): bool
    {
        // فقط سرمایه‌گذار می‌تواند پرداخت کند
        return $investment->investor_type === User::class && $investment->investor_id === $user->id;
    }

    /**
     * لغو سرمایه‌گذاری
     */
    public function delete(User $user, Investment $investment): bool
    {
        // فقط سرمایه‌گذار می‌تواند لغو کند
        if ($investment->investor_type === User::class && $investment->investor_id === $user->id) {
            // فقط pending و paid قابل لغو هستند
            return in_array($investment->status, ['pending', 'paid']);
        }

        return false;
    }
}
