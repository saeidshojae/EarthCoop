<?php

namespace App\Modules\NajmBahar\Models;

use App\Models\User;
use App\Models\Group;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Investment extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'najm_bahar_investments';

    protected $fillable = [
        'project_id',
        'investor_type',
        'investor_id',
        'amount',
        'agreed_profit_percentage',
        'expected_return',
        'transaction_id',
        'transaction_tracking',
        'status',
        'invested_at',
        'maturity_date',
        'completed_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'expected_return' => 'integer',
        'agreed_profit_percentage' => 'decimal:2',
        'invested_at' => 'datetime',
        'maturity_date' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * پروژه مرتبط
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * سرمایه‌گذار (User یا Group) - Polymorphic
     */
    public function investor()
    {
        return $this->morphTo();
    }

    /**
     * تراکنش نجم بهار مرتبط
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * اسکوپ برای سرمایه‌گذاری‌های فعال
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * اسکوپ برای سرمایه‌گذاری‌های تکمیل شده
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * اسکوپ برای سرمایه‌گذاری‌های پرداخت شده
     */
    public function scopePaid($query)
    {
        return $query->whereIn('status', ['paid', 'active', 'completed']);
    }

    /**
     * دریافت نام فارسی وضعیت
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'در انتظار پرداخت',
            'paid' => 'پرداخت شده',
            'active' => 'فعال',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'refunded' => 'بازگشت داده شده',
            default => 'نامشخص',
        };
    }

    /**
     * دریافت رنگ badge برای وضعیت
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active' => 'green',
            'completed' => 'blue',
            'paid' => 'purple',
            'pending' => 'amber',
            'cancelled', 'refunded' => 'red',
            default => 'gray',
        };
    }

    /**
     * محاسبه سود پیش‌بینی شده
     */
    public function calculateExpectedReturn(): int
    {
        if (!$this->agreed_profit_percentage) {
            return 0;
        }

        $profit = ($this->amount * $this->agreed_profit_percentage) / 100;
        return (int) ($this->amount + $profit);
    }

    /**
     * تعریف Factory برای تست
     */
    protected static function newFactory()
    {
        return \Database\Factories\Modules\NajmBahar\Models\InvestmentFactory::new();
    }
}
