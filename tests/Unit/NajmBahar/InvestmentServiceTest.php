<?php

namespace Tests\Unit\NajmBahar;

use Tests\TestCase;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\Investment;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\ProjectService;
use App\Modules\NajmBahar\Services\InvestmentService;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class InvestmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvestmentService $investmentService;
    protected User $investor;
    protected User $projectOwner;
    protected Project $project;
    protected AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->investmentService = app(InvestmentService::class);
        $this->accountService = app(AccountService::class);

        // ایجاد کاربران
        $this->investor = User::factory()->create(['email_verified_at' => now()]);
        $this->projectOwner = User::factory()->create(['email_verified_at' => now()]);

        // ایجاد حساب‌ها
        $this->accountService->createMainAccountForUser($this->investor->id);
        $this->accountService->createMainAccountForUser($this->projectOwner->id);

        // ایجاد پروژه تایید شده
        $category = ProjectCategory::create([
            'name' => 'انرژی',
            'level' => 1,
        ]);

        $this->project = app(ProjectService::class)->createProject($this->projectOwner, [
            'title' => 'پروژه انرژی خورشیدی',
            'category_level1_id' => $category->id,
            'summary' => 'خلاصه پروژه',
            'description' => 'توضیحات کامل',
            'required_capital' => 100000000, // 1,000,000 گل
            'profit_percentage' => 25,
            'investment_duration_months' => 24,
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'نیاز اقتصادی روشن برای پروژه',
            'solution_description' => 'راه‌حل اجرایی پروژه',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,
        ]);

        // تایید پروژه
        app(ProjectService::class)->submitForReview($this->project);
        $admin = User::factory()->create(['is_admin' => true]);
        app(ProjectService::class)->startReview($this->project, $admin);
        app(ProjectService::class)->approveProject($this->project, $admin);

        Notification::fake();
    }

    /** @test */
    public function it_can_create_investment()
    {
        $investment = $this->investmentService->createInvestment(
            $this->project,
            $this->investor,
            10000000 // 100,000 گل
        );

        $this->assertInstanceOf(Investment::class, $investment);
        $this->assertEquals('pending', $investment->status);
        $this->assertEquals(10000000, $investment->amount);
        $this->assertEquals($this->investor->id, $investment->investor_id);
        $this->assertEquals($this->project->id, $investment->project_id);
        $this->assertEquals(25, $investment->agreed_profit_percentage);
    }

    /** @test */
    public function it_cannot_invest_in_non_approved_project()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('فقط پروژه‌های تایید شده قابل سرمایه‌گذاری هستند.');

        $draftProject = app(ProjectService::class)->createProject($this->projectOwner, [
            'title' => 'پروژه پیش‌نویس',
            'category_level1_id' => $this->project->category_level1_id,
            'summary' => 'خلاصه',
            'description' => 'توضیحات',
            'required_capital' => 5000000,
            'profit_percentage' => 10,
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'active',
            'investment_method' => 'capital_participation',
            'problem_statement' => 'نیاز اقتصادی روشن برای پروژه',
            'solution_description' => 'راه‌حل اجرایی پروژه',
            'target_market' => 'general',
            'accept_transparency' => true,
            'accept_rules' => true,
        ]);

        $this->investmentService->createInvestment($draftProject, $this->investor, 1000000);
    }

    /** @test */
    public function it_cannot_invest_more_than_required_capital()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('مبلغ سرمایه‌گذاری از سقف مورد نیاز پروژه بیشتر است.');

        $this->investmentService->createInvestment(
            $this->project,
            $this->investor,
            150000000 // بیش از سقف پروژه
        );
    }

    /** @test */
    public function it_can_process_investment_payment()
    {
        // شارژ حساب سرمایه‌گذار
        $investorAccount = $this->accountService->getMainAccountForUser($this->investor->id);
        $investorAccount->balance = 50000000; // 500,000 گل
        $investorAccount->save();

        $investment = $this->investmentService->createInvestment(
            $this->project,
            $this->investor,
            20000000 // 200,000 گل
        );

        $paid = $this->investmentService->processInvestmentPayment($investment, $this->investor);

        $this->assertEquals('paid', $paid->status);
        $this->assertNotNull($paid->invested_at);
        $this->assertNotNull($paid->transaction_id);

        // بررسی بروزرسانی موجودی
        $this->assertEquals(30000000, $investorAccount->fresh()->balance); // 500,000 - 200,000

        // بررسی ارسال اعلان‌ها
        Notification::assertSentTo($this->investor, \App\Notifications\NajmBahar\InvestmentStatusChanged::class);
        Notification::assertSentTo($this->projectOwner, \App\Notifications\NajmBahar\NewInvestmentReceived::class);
    }

    /** @test */
    public function it_cannot_process_payment_twice()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('سرمایه‌گذاری قبلاً پرداخت شده است.');

        // شارژ حساب
        $investorAccount = $this->accountService->getMainAccountForUser($this->investor->id);
        $investorAccount->balance = 50000000;
        $investorAccount->save();

        $investment = $this->investmentService->createInvestment(
            $this->project,
            $this->investor,
            10000000
        );

        $this->investmentService->processInvestmentPayment($investment, $this->investor);
        
        // تلاش برای پرداخت مجدد
        $this->investmentService->processInvestmentPayment($investment, $this->investor);
    }

    /** @test */
    public function it_can_activate_investment()
    {
        // شارژ و پرداخت
        $investorAccount = $this->accountService->getMainAccountForUser($this->investor->id);
        $investorAccount->balance = 30000000;
        $investorAccount->save();

        $investment = $this->investmentService->createInvestment($this->project, $this->investor, 15000000);
        $this->investmentService->processInvestmentPayment($investment, $this->investor);

        $activated = $this->investmentService->activateInvestment($investment->fresh());

        $this->assertEquals('active', $activated->status);
        $this->assertNotEmpty($activated->metadata['activated_at'] ?? null);
        
        Notification::assertSentTo($this->investor, \App\Notifications\NajmBahar\InvestmentStatusChanged::class);
    }

    /** @test */
    public function it_can_complete_investment()
    {
        // شارژ، پرداخت و فعال‌سازی
        $investorAccount = $this->accountService->getMainAccountForUser($this->investor->id);
        $investorAccount->balance = 25000000;
        $investorAccount->save();

        $projectOwnerAccount = $this->accountService->getMainAccountForUser($this->projectOwner->id);
        $projectOwnerAccount->balance = 100000000; // برای بازگشت سرمایه
        $projectOwnerAccount->save();

        $investment = $this->investmentService->createInvestment($this->project, $this->investor, 10000000);
        $this->investmentService->processInvestmentPayment($investment, $this->investor);
        $this->investmentService->activateInvestment($investment->fresh());

        $completed = $this->investmentService->completeInvestment($investment->fresh());

        $this->assertEquals('completed', $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertEquals($completed->expected_return, $completed->metadata['actual_return']);
        
        // بررسی بازگشت سرمایه + سود (10,000,000 + 25% = 12,500,000)
        $this->assertEquals(12500000, $completed->metadata['actual_return']);
    }

    /** @test */
    public function it_can_cancel_pending_investment()
    {
        $investment = $this->investmentService->createInvestment($this->project, $this->investor, 5000000);

        $cancelled = $this->investmentService->cancelInvestment($investment, 'لغو توسط کاربر', false);

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('لغو توسط کاربر', $cancelled->notes);
        
        Notification::assertSentTo($this->investor, \App\Notifications\NajmBahar\InvestmentStatusChanged::class);
    }

    /** @test */
    public function it_can_refund_paid_investment()
    {
        // شارژ و پرداخت
        $investorAccount = $this->accountService->getMainAccountForUser($this->investor->id);
        $investorAccount->balance = 40000000;
        $investorAccount->save();

        $investment = $this->investmentService->createInvestment($this->project, $this->investor, 20000000);
        $this->investmentService->processInvestmentPayment($investment, $this->investor);

        // موجودی قبل از بازگشت
        $balanceBefore = $investorAccount->fresh()->balance; // 40M - 20M = 20M

        $refunded = $this->investmentService->cancelInvestment(
            $investment->fresh(), 
            'بازگشت وجه', 
            true
        );

        $this->assertEquals('refunded', $refunded->status);
        
        // بررسی بازگشت وجه
        $this->assertEquals($balanceBefore + 20000000, $investorAccount->fresh()->balance);
        
        // بررسی کاهش total_invested
        $this->assertEquals(0, $this->project->fresh()->total_invested);
    }

    /** @test */
    public function it_can_get_investments_by_investor()
    {
        $this->investmentService->createInvestment($this->project, $this->investor, 5000000);
        $this->investmentService->createInvestment($this->project, $this->investor, 8000000);

        $investments = $this->investmentService->getInvestmentsByInvestor($this->investor);

        $this->assertCount(2, $investments);
    }

    /** @test */
    public function it_can_get_investments_by_project()
    {
        $investor2 = User::factory()->create();
        $this->accountService->createMainAccountForUser($investor2->id);

        $this->investmentService->createInvestment($this->project, $this->investor, 3000000);
        $this->investmentService->createInvestment($this->project, $investor2, 7000000);

        $investments = $this->investmentService->getInvestmentsByProject($this->project);

        $this->assertCount(2, $investments);
    }

    /** @test */
    public function it_can_get_project_investment_stats()
    {
        // شارژ حساب
        $investorAccount = $this->accountService->getMainAccountForUser($this->investor->id);
        $investorAccount->balance = 100000000;
        $investorAccount->save();

        // ایجاد چند سرمایه‌گذاری
        $inv1 = $this->investmentService->createInvestment($this->project, $this->investor, 20000000);
        $inv2 = $this->investmentService->createInvestment($this->project, $this->investor, 30000000);
        $this->investmentService->processInvestmentPayment($inv2, $this->investor);

        $stats = $this->investmentService->getProjectInvestmentStats($this->project);

        $this->assertEquals(2, $stats['total_investments']);
        $this->assertEquals(30000000, $stats['total_amount']); // فقط paid
        $this->assertEquals(1, $stats['pending_count']);
        $this->assertEquals(1, $stats['paid_count']);
        $this->assertEquals(70000000, $stats['remaining_capital']); // 100M - 30M
    }
}
