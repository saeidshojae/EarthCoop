<?php

namespace Tests\Feature\NajmBahar;

use Tests\TestCase;
use App\Models\User;
use App\Models\Setting;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\Investment;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\ProjectService;
use App\Modules\NajmBahar\Services\InvestmentService;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class InvestmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $investor;
    protected User $projectOwner;
    protected Project $project;
    protected AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountService = app(AccountService::class);

        Setting::query()->updateOrCreate(['id' => 1], [
            'najm_bahar_user_threshold' => 1,
        ]);

        // ایجاد کاربران تست
        $this->investor = User::factory()->create(['email_verified_at' => now()]);
        $this->projectOwner = User::factory()->create(['email_verified_at' => now()]);

        // ایجاد حساب‌ها
        $this->accountService->createMainAccountForUser($this->investor->id);
        $this->accountService->createMainAccountForUser($this->projectOwner->id);

        // ایجاد دسته‌بندی و پروژه تایید شده
        $category = ProjectCategory::create([
            'name' => 'صنعت',
            'level' => 1,
        ]);

        $this->project = app(ProjectService::class)->createProject($this->projectOwner, [
            'title' => 'پروژه صنعتی تست',
            'category_level1_id' => $category->id,
            'summary' => 'خلاصه پروژه',
            'description' => 'توضیحات کامل پروژه',
            'required_capital' => 50000000, // 500,000 گل
            'profit_percentage' => 20,
            'investment_duration_months' => 18,
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
    public function user_can_view_investment_opportunities()
    {
        $response = $this->actingAs($this->investor)
            ->get(route('najm-bahar.investments.index'));

        $response->assertOk()
            ->assertViewIs('najm-bahar.investments.index')
            ->assertViewHas('projects');
    }

    /** @test */
    public function user_can_view_project_for_investment()
    {
        $response = $this->actingAs($this->investor)
            ->get(route('najm-bahar.investments.show', $this->project));

        $response->assertOk()
            ->assertViewIs('najm-bahar.investments.show')
            ->assertViewHas('project', $this->project);
    }

    /** @test */
    public function user_can_create_investment()
    {
        $data = [
            'amount' => 10000000, // 100,000 گل
        ];

        $response = $this->actingAs($this->investor)
            ->post(route('najm-bahar.investments.store', $this->project), $data);

        $response->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('najm_bahar_investments', [
            'investor_type' => User::class,
            'investor_id' => $this->investor->id,
            'project_id' => $this->project->id,
            'amount' => 10000000, // در گل
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function user_cannot_invest_more_than_required_capital()
    {
        $data = [
            'amount' => 60000000, // بیش از سقف پروژه (500,000)
        ];

        $response = $this->actingAs($this->investor)
            ->post(route('najm-bahar.investments.store', $this->project), $data);

        $response->assertSessionHas('error');
    }

    /** @test */
    public function user_can_view_payment_page()
    {
        $investment = app(InvestmentService::class)->createInvestment(
            $this->project,
            $this->investor,
            10000000 // 100,000 گل
        );

        $response = $this->actingAs($this->investor)
            ->get(route('najm-bahar.investments.payment', $investment));

        $response->assertOk()
            ->assertViewIs('najm-bahar.investments.payment')
            ->assertViewHas('investment', $investment);
    }

    /** @test */
    public function user_can_process_payment_with_sufficient_balance()
    {
        // شارژ حساب سرمایه‌گذار
        $investorAccount = $this->accountService->getMainAccountForUser($this->investor->id);
        $investorAccount->balance = 20000000; // 200,000 گل
        $investorAccount->balance_active = 20000000;
        $investorAccount->save();

        $investment = app(InvestmentService::class)->createInvestment(
            $this->project,
            $this->investor,
            10000000
        );

        $response = $this->actingAs($this->investor)
            ->post(route('najm-bahar.investments.process-payment', $investment));

        $response->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('paid', $investment->fresh()->status);
        $this->assertNotNull($investment->fresh()->transaction_id);
    }

    /** @test */
    public function user_cannot_process_payment_with_insufficient_balance()
    {
        // موجودی ناکافی
        $investment = app(InvestmentService::class)->createInvestment(
            $this->project,
            $this->investor,
            10000000
        );

        $response = $this->actingAs($this->investor)
            ->post(route('najm-bahar.investments.process-payment', $investment));

        $response->assertSessionHas('error');
        $this->assertEquals('pending', $investment->fresh()->status);
    }

    /** @test */
    public function user_can_view_my_investments()
    {
        $investment1 = app(InvestmentService::class)->createInvestment(
            $this->project,
            $this->investor,
            5000000
        );

        $investment2 = app(InvestmentService::class)->createInvestment(
            $this->project,
            $this->investor,
            3000000
        );

        $response = $this->actingAs($this->investor)
            ->get(route('najm-bahar.investments.my-investments'));

        $response->assertOk()
            ->assertViewIs('najm-bahar.investments.my-investments')
            ->assertViewHas('investments');
    }

    /** @test */
    public function user_can_view_single_investment()
    {
        $investment = app(InvestmentService::class)->createInvestment(
            $this->project,
            $this->investor,
            8000000
        );

        $response = $this->actingAs($this->investor)
            ->get(route('najm-bahar.investments.show-investment', $investment));

        $response->assertOk()
            ->assertViewIs('najm-bahar.investments.show-investment')
            ->assertViewHas('investment', $investment);
    }

    /** @test */
    public function user_can_cancel_pending_investment()
    {
        $investment = app(InvestmentService::class)->createInvestment(
            $this->project,
            $this->investor,
            2000000
        );

        $response = $this->actingAs($this->investor)
            ->post(route('najm-bahar.investments.cancel', $investment), [
                'reason' => 'لغو توسط کاربر',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('cancelled', $investment->fresh()->status);
    }

    /** @test */
    public function user_can_cancel_paid_investment_with_refund()
    {
        // شارژ حساب
        $investorAccount = $this->accountService->getMainAccountForUser($this->investor->id);
        $investorAccount->balance = 15000000;
        $investorAccount->balance_active = 15000000;
        $investorAccount->save();

        $investment = app(InvestmentService::class)->createInvestment(
            $this->project,
            $this->investor,
            10000000
        );

        app(InvestmentService::class)->processInvestmentPayment($investment, $this->investor);

        $response = $this->actingAs($this->investor)
            ->post(route('najm-bahar.investments.cancel', $investment), [
                'reason' => 'لغو و بازگشت وجه',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('refunded', $investment->fresh()->status);
    }

    /** @test */
    public function user_cannot_view_others_investment()
    {
        $otherUser = User::factory()->create();

        $investment = app(InvestmentService::class)->createInvestment(
            $this->project,
            $this->investor,
            5000000
        );

        $response = $this->actingAs($otherUser)
            ->get(route('najm-bahar.investments.show-investment', $investment));

        $response->assertForbidden();
    }
}
