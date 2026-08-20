<?php

namespace App\Modules\Stock\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\StockPayeeAccount;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockPayeeAccountService
{
    public function resolvePrimary(Stock $stock): Account
    {
        if ((string)$stock->issuer_type === SettlementEligibilityPolicy::ISSUER_EARTHCOOP) {
            $number=(string)config('stock.earthcoop_capital_account_number','');
            if($number==='') throw new RuntimeException('EarthCoop capital Najm Bahar account is not configured.');
            $account=Account::query()->where('account_number',$number)->where('status',1)->first();
            if(!$account) throw new RuntimeException('EarthCoop capital Najm Bahar account is missing or inactive.');
            return $account;
        }

        if ((string)$stock->issuer_type === SettlementEligibilityPolicy::ISSUER_PROJECT) {
            $mapping=StockPayeeAccount::query()->with('account')->where('stock_id',$stock->id)
                ->where('purpose','primary_capital')->where('is_active',true)->first();
            if(!$mapping || !$mapping->account || !(bool)$mapping->account->status) {
                throw new RuntimeException('Project Stock has no active canonical primary-capital payee account mapping.');
            }
            return $mapping->account;
        }

        throw new RuntimeException('Canonical primary payee account is not defined for this issuer type.');
    }

    public function configureProject(Stock $stock, Account $account, ?int $actorId=null, array $metadata=[]): StockPayeeAccount
    {
        if ((string)$stock->issuer_type !== SettlementEligibilityPolicy::ISSUER_PROJECT) {
            throw new RuntimeException('Explicit Stock payee mapping is reserved for project issuers.');
        }
        if (!(bool)$account->status) throw new RuntimeException('Payee Najm Bahar account must be active.');
        if (! in_array((string)$account->type,['legal_entity','project','central'],true)) {
            throw new RuntimeException('Project capital payee must use a non-personal Najm Bahar account.');
        }

        return DB::transaction(function () use($stock,$account,$actorId,$metadata){
            return StockPayeeAccount::query()->updateOrCreate(
                ['stock_id'=>$stock->id],
                ['account_id'=>$account->id,'purpose'=>'primary_capital','is_active'=>true,'configured_by'=>$actorId,'verified_at'=>now(),'metadata'=>$metadata]
            );
        });
    }
}
