<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Group;
use App\Models\User;
use App\Models\OccupationalField;

class NajmBaharProject extends Model
{
    protected $fillable = [
        'user_id',
        'group_id',
        'occupational_field_id',
        'title',
        'project_type',
        'profit_percent',
        'summary',
        'full_plan_path',
        'full_plan_original_name',
        'full_plan_mime',
        'full_plan_size',
        'investment_amount',
        'duration_months',
        'status',
        'rejection_reason',
        'approved_at',
        'rejected_at',
        'archived_at',
        'resubmitted_at',
    ];

    protected $casts = [
        'profit_percent' => 'decimal:2',
        'investment_amount' => 'integer',
        'duration_months' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'archived_at' => 'datetime',
        'resubmitted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function category()
    {
        return $this->belongsTo(OccupationalField::class, 'occupational_field_id');
    }

    public function investments()
    {
        return $this->hasMany(NajmBaharProjectInvestment::class, 'project_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function categoryPath(): string
    {
        $category = $this->category;
        if (! $category) {
            return '-';
        }

        $parts = [$category->name];
        $parent = $category->parent;

        while ($parent) {
            array_unshift($parts, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' / ', $parts);
    }
}
