<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\User;
use App\Models\Group;
use App\Modules\NajmBahar\Models\Investment;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Notifications\NajmBahar\InvestmentStatusChanged;
use App\Notifications\NajmBahar\NewInvestmentReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class InvestmentService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * ایجاد سرمایه‌گذاری جدید
     *
     * @param Project $project پروژه
     * @param User|Group $investor سرمایه‌گذار
     * @param int $amount مبلغ (به گل)
     * @param array $options اطلاعات تکمیلی
     * @return Investment
     */
    public function createInvestment(Project $project, $investor, int $amount, array $options = []): Investment
    {
        if ($project->status !== 'approved') {
            throw new \Exception('فقط پروژه‌های تایید شده قابل سرمایه‌گذاری هستند.');
        }

        if ($amount <= 0) {
            throw new \Exception('مبلغ سرمایه‌گذاری باید مثبت باشد.');
        }

        // بررسی سقف سرمایه
        if (($project->total_invested + $amount) > $project->required_capital) {
            throw new \Exception('مبلغ سرمایه‌گذاری از سقف مورد نیاز پروژه بیشتر است.');
        }

        return DB::transaction(function () use ($investor, $project, $amount, $options) {
            $investment = new Investment([
                'project_id' => $project->id,
                'amount' => $amount,
                'agreed_profit_percentage' => $project->profit_percentage,
                'expected_return' => $amount + ($amount * $project->profit_percentage / 100),
                'status' => 'pending', // منتظر پرداخت
                'notes' => $options['notes'] ?? null,
            ]);

            $investment->investor()->associate($investor);
            $investment->save();

            return $investment;
        });
    }

    /**
     * پردازش پرداخت سرمایه‌گذاری
     *
     * @param Investment $investment
     * @param User|Group $payer
     * @param string|null $trackingCode
     * @return Investment
     */
    public function processInvestmentPayment(Investment $investment, $payer, ?string $trackingCode = null): Investment
    {
        if ($investment->status !== 'pending') {
            throw new \Exception('سرمایه‌گذاری قبلاً پرداخت شده است.');
        }

        if (get_class($payer) !== $investment->investor_type || $payer->id !== $investment->investor_id) {
            throw new \Exception('پرداخت‌کننده با سرمایه‌گذار مطابقت ندارد.');
        }

        return DB::transaction(function () use ($investment, $trackingCode) {
            // حساب سرمایه‌گذار
            $investorAccountNumber = $investment->investor_type === User::class
                ? AccountNumberService::makeMainAccountNumberForUser($investment->investor_id)
                : AccountNumberService::makeMainAccountNumberForGroup($investment->investor_id);

            // حساب پروژه (از حساب مالک پروژه)
            $projectOwnerAccountNumber = $investment->project->owner_type === User::class
                ? AccountNumberService::makeMainAccountNumberForUser($investment->project->owner_id)
                : AccountNumberService::makeMainAccountNumberForGroup($investment->project->owner_id);

            // انتقال وجه
            $transaction = $this->transactionService->transfer(
                $investorAccountNumber,
                $projectOwnerAccountNumber,
                $investment->amount,
                "سرمایه‌گذاری در پروژه: {$investment->project->title}",
                [
                    'investment_id' => $investment->id,
                    'project_id' => $investment->project_id,
                ],
                "investment-{$investment->id}"
            );

            // بروزرسانی سرمایه‌گذاری
            $investment->status = 'paid';
            $investment->invested_at = now();
            $investment->maturity_date = $investment->project->investment_duration_months
                ? now()->addMonths($investment->project->investment_duration_months)
                : null;
            $investment->transaction_id = $transaction->id;
            $investment->transaction_tracking = $trackingCode;
            $investment->save();

            // ارسال اعلان به سرمایه‌گذار
            $investment->investor->notify(new InvestmentStatusChanged($investment, 'paid'));

            // ارسال اعلان به صاحب پروژه
            $investment->project->owner->notify(new NewInvestmentReceived($investment));

            return $investment->fresh();
        });
    }

    /**
     * فعال‌سازی سرمایه‌گذاری (پس از تایید پرداخت)
     *
     * @param Investment $investment
     * @return Investment
     */
    public function activateInvestment(Investment $investment): Investment
    {
        if ($investment->status !== 'paid') {
            throw new \Exception('فقط سرمایه‌گذاری‌های پرداخت شده قابل فعال‌سازی هستند.');
        }

        return DB::transaction(function () use ($investment) {
            $investment->status = 'active';
            $metadata = $investment->metadata ?? [];
            $metadata['activated_at'] = now()->toDateTimeString();
            $investment->metadata = $metadata;
            $investment->save();

            // ارسال اعلان
            $investment->investor->notify(new InvestmentStatusChanged($investment, 'active'));

            return $investment->fresh();
        });
    }

    /**
     * تکمیل سرمایه‌گذاری و پرداخت سود
     *
     * @param Investment $investment
     * @param int|null $actualReturn مبلغ واقعی بازگشتی (اختیاری)
     * @return Investment
     */
    public function completeInvestment(Investment $investment, ?int $actualReturn = null): Investment
    {
        if ($investment->status !== 'active') {
            throw new \Exception('فقط سرمایه‌گذاری‌های فعال قابل تکمیل هستند.');
        }

        return DB::transaction(function () use ($investment, $actualReturn) {
            // محاسبه مبلغ بازگشتی
            $returnAmount = $actualReturn ?? $investment->expected_return;

            // حساب پروژه (صاحب پروژه)
            $projectOwnerAccountNumber = $investment->project->owner_type === User::class
                ? AccountNumberService::makeMainAccountNumberForUser($investment->project->owner_id)
                : AccountNumberService::makeMainAccountNumberForGroup($investment->project->owner_id);

            // حساب سرمایه‌گذار
            $investorAccountNumber = $investment->investor_type === User::class
                ? AccountNumberService::makeMainAccountNumberForUser($investment->investor_id)
                : AccountNumberService::makeMainAccountNumberForGroup($investment->investor_id);

            // انتقال سود
            $transaction = $this->transactionService->transfer(
                $projectOwnerAccountNumber,
                $investorAccountNumber,
                $returnAmount,
                "بازگشت سرمایه و سود پروژه: {$investment->project->title}",
                [
                    'investment_id' => $investment->id,
                    'project_id' => $investment->project_id,
                    'return_type' => 'completed',
                ],
                "investment-return-{$investment->id}"
            );

            // بروزرسانی سرمایه‌گذاری
            $investment->status = 'completed';
            $investment->completed_at = now();
            $metadata = $investment->metadata ?? [];
            $metadata['actual_return'] = $returnAmount;
            $investment->metadata = $metadata;
            $investment->save();

            // ارسال اعلان
            $investment->investor->notify(new InvestmentStatusChanged($investment, 'completed', "مبلغ بازگشتی: {$returnAmount} گل"));

            return $investment->fresh();
        });
    }

    /**
     * لغو سرمایه‌گذاری
     *
     * @param Investment $investment
     * @param string|null $reason دلیل لغو
     * @param bool $refund بازگشت وجه
     * @return Investment
     */
    public function cancelInvestment(Investment $investment, ?string $reason = null, bool $refund = true): Investment
    {
        if (in_array($investment->status, ['completed', 'cancelled', 'refunded'])) {
            throw new \Exception('این سرمایه‌گذاری قابل لغو نیست.');
        }

        return DB::transaction(function () use ($investment, $reason, $refund) {
            // بازگشت وجه در صورت تایید
            if ($refund && in_array($investment->status, ['paid', 'active'])) {
                // حساب پروژه
                $projectOwnerAccountNumber = $investment->project->owner_type === User::class
                    ? AccountNumberService::makeMainAccountNumberForUser($investment->project->owner_id)
                    : AccountNumberService::makeMainAccountNumberForGroup($investment->project->owner_id);

                // حساب سرمایه‌گذار
                $investorAccountNumber = $investment->investor_type === User::class
                    ? AccountNumberService::makeMainAccountNumberForUser($investment->investor_id)
                    : AccountNumberService::makeMainAccountNumberForGroup($investment->investor_id);

                // بازگشت اصل سرمایه
                $this->transactionService->transfer(
                    $projectOwnerAccountNumber,
                    $investorAccountNumber,
                    $investment->amount,
                    "بازگشت سرمایه: {$investment->project->title} - {$reason}",
                    [
                        'investment_id' => $investment->id,
                        'project_id' => $investment->project_id,
                        'return_type' => 'refund',
                    ],
                    "investment-refund-{$investment->id}"
                );

                $investment->status = 'refunded';
            } else {
                $investment->status = 'cancelled';
            }

            $investment->notes = $reason;
            $investment->save();

            // ارسال اعلان
            $investment->investor->notify(new InvestmentStatusChanged($investment, $investment->status, $reason));

            return $investment->fresh();
        });
    }

    /**
     * دریافت سرمایه‌گذاری‌های سرمایه‌گذار
     *
     * @param User|Group $investor
     * @param array $statuses
     * @return Collection
     */
    public function getInvestmentsByInvestor($investor, array $statuses = []): Collection
    {
        $query = $investor->najmBaharInvestments()->with(['project', 'transaction']);

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * دریافت سرمایه‌گذاری‌های یک پروژه
     *
     * @param Project $project
     * @param array $statuses
     * @return Collection
     */
    public function getInvestmentsByProject(Project $project, array $statuses = []): Collection
    {
        $query = $project->investments()->with(['investor', 'transaction']);

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * دریافت آمار سرمایه‌گذاری پروژه
     *
     * @param Project $project
     * @return array
     */
    public function getProjectInvestmentStats(Project $project): array
    {
        return [
            'total_investments' => $project->investments()->count(),
            'total_amount' => $project->total_invested,
            'pending_count' => $project->investments()->where('status', 'pending')->count(),
            'paid_count' => $project->investments()->where('status', 'paid')->count(),
            'active_count' => $project->investments()->where('status', 'active')->count(),
            'completed_count' => $project->investments()->where('status', 'completed')->count(),
            'cancelled_count' => $project->investments()->whereIn('status', ['cancelled', 'refunded'])->count(),
            'investment_progress' => $project->investment_progress,
            'remaining_capital' => $project->required_capital - $project->total_invested,
        ];
    }

    /**
     * دریافت آمار سرمایه‌گذاری‌های سرمایه‌گذار
     *
     * @param User|Group $investor
     * @return array
     */
    public function getInvestorStats($investor): array
    {
        $query = $investor->najmBaharInvestments();

        return [
            'total' => $query->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'paid' => (clone $query)->where('status', 'paid')->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'refunded' => (clone $query)->where('status', 'refunded')->count(),
        ];
    }
}
