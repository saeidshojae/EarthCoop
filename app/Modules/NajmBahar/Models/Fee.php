<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $table = 'najm_bahar_fees';
    
    protected $fillable = [
        'name',
        'type', // fixed, percentage, combined
        'fixed_amount',
        'percentage',
        'transaction_type', // all, immediate, scheduled, fee, adjustment
        'min_amount',
        'max_amount',
        'is_active',
        'description',
    ];
    
    protected $casts = [
        'fixed_amount' => 'integer',
        'percentage' => 'decimal:2',
        'min_amount' => 'integer',
        'max_amount' => 'integer',
        'is_active' => 'boolean',
    ];
    
    /**
     * محاسبه کارمزد برای یک مبلغ
     */
    public function calculate(int $amount): int
    {
        if (!$this->is_active) {
            return 0;
        }
        
        // بررسی محدودیت مبلغ
        if ($this->min_amount && $amount < $this->min_amount) {
            return 0;
        }
        
        if ($this->max_amount && $amount > $this->max_amount) {
            return 0;
        }
        
        $fee = 0;
        
        switch ($this->type) {
            case 'fixed':
                $fee = $this->fixed_amount ?? 0;
                break;
                
            case 'percentage':
                $fee = (int) round($amount * ($this->percentage / 100));
                break;
                
            case 'combined':
                $fixed = $this->fixed_amount ?? 0;
                $percentage = (int) round($amount * ($this->percentage / 100));
                $fee = $fixed + $percentage;
                break;
        }
        
        return max(0, $fee);
    }
    
    /**
     * بررسی اعمال کارمزد برای نوع تراکنش
     */
    public function appliesTo(string $transactionType): bool
    {
        if ($this->transaction_type === 'all') {
            return true;
        }
        
        return $this->transaction_type === $transactionType;
    }
}

