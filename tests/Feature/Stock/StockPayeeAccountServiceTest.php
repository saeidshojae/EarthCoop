<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\StockPayeeAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockPayeeAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_payee_mapping_requires_non_personal_active_account(): void
    {
        $stock=$this->projectStock();
        $user=User::factory()->create();
        $personal=Account::create([
            'account_number'=>'1000000001','user_id'=>$user->id,'name'=>'personal','type'=>'user',
            'balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1,
        ]);

        $this->expectException(\RuntimeException::class);
        app(StockPayeeAccountService::class)->configureProject($stock,$personal,$user->id);
    }

    public function test_project_payee_mapping_resolves_only_after_explicit_configuration(): void
    {
        $stock=$this->projectStock();
        $service=app(StockPayeeAccountService::class);
        try {
            $service->resolvePrimary($stock);
            $this->fail('Expected missing mapping to fail closed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no active canonical',strtolower($e->getMessage()));
        }

        $capital=Account::create([
            'account_number'=>'2000000001','name'=>'project capital','type'=>'legal_entity',
            'balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1,
        ]);
        $mapping=$service->configureProject($stock,$capital,null,['source'=>'test']);

        $this->assertSame($capital->id,$mapping->account_id);
        $this->assertSame($capital->id,$service->resolvePrimary($stock)->id);
    }

    private function projectStock(): Stock
    {
        return Stock::create([
            'issuer_type'=>'project','issuer_id'=>77,
            'startup_valuation'=>1000,'startup_valuation_gol'=>1000,
            'total_shares'=>100,'available_shares'=>100,
            'base_share_price'=>1,'base_share_price_gol'=>10,
        ]);
    }
}
