<?php

namespace App\Modules\NajmBahar\Models;

use App\Models\GovernanceArea;
use App\Models\User;
use App\Models\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'najm_bahar_projects';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'governance_area_id',
        'target_location_id',
        'category_level1_id',
        'category_level2_id',
        'category_level3_id',
        // Legacy geographic scope fields retained for rollback during canonical cutover.
        'geographic_continent_id',
        'geographic_country_id',
        'geographic_province_id',
        'geographic_county_id',
        'geographic_section_id',
        'geographic_city_id',
        'geographic_rural_id',
        'geographic_region_id',
        'geographic_neighborhood_id',
        'geographic_street_id',
        'geographic_alley_id',
        'title',
        'project_type',
        'project_visibility',
        'project_stage',
        'investment_method',
        'existing_assets',
        'summary',
        'description',
        'problem_statement',
        'solution_description',
        'value_proposition',
        'target_market',
        'base_value_min',
        'base_value_max',
        'required_capital',
        'profit_percentage',
        'investment_duration_months',
        'total_shares',
        'initial_auction_percent',
        'max_user_ownership_percent',
        'auction_period',
        'risk_level',
        'main_risks',
        'oversight_type',
        'reporting_interval',
        'fund_usage_scope',
        'accept_transparency',
        'failure_policy',
        'value_update_trigger',
        'accept_rules',
        'approved_value_min',
        'approved_value_max',
        'current_base_value',
        'current_market_price',
        'audit_log',
        'attachments',
        'status',
        'admin_notes',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'archived_at',
        'assigned_to_type',
        'assigned_to_id',
        'assigned_at',
        'assignment_note',
        'assignment_status',
        'assignment_review_note',
        'assignment_completed_at',
    ];

    protected $casts = [
        'required_capital' => 'integer',
        'profit_percentage' => 'decimal:2',
        'investment_duration_months' => 'integer',
        'base_value_min' => 'integer',
        'base_value_max' => 'integer',
        'total_shares' => 'integer',
        'initial_auction_percent' => 'decimal:2',
        'max_user_ownership_percent' => 'decimal:2',
        'main_risks' => 'array',
        'accept_transparency' => 'boolean',
        'accept_rules' => 'boolean',
        'approved_value_min' => 'integer',
        'approved_value_max' => 'integer',
        'current_base_value' => 'integer',
        'current_market_price' => 'integer',
        'audit_log' => 'array',
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'archived_at' => 'datetime',
        'assigned_at' => 'datetime',
        'assignment_completed_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->morphTo();
    }

    public function governanceArea()
    {
        return $this->belongsTo(GovernanceArea::class);
    }

    public function assignedTo()
    {
        return $this->morphTo('assigned_to');
    }

    public function categoryLevel1()
    {
        return $this->belongsTo(ProjectCategory::class, 'category_level1_id');
    }

    public function categoryLevel2()
    {
        return $this->belongsTo(ProjectCategory::class, 'category_level2_id');
    }

    public function categoryLevel3()
    {
        return $this->belongsTo(ProjectCategory::class, 'category_level3_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProjectReview::class)->orderBy('created_at', 'desc');
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopePublic($query)
    {
        return $query->where('project_visibility', 'public');
    }

    public function scopePrivate($query)
    {
        return $query->where('project_visibility', 'private');
    }

    public function getTotalInvestedAttribute(): int
    {
        return $this->investments()
            ->whereIn('status', ['paid', 'active', 'completed'])
            ->sum('amount');
    }

    public function getInvestmentProgressAttribute(): float
    {
        if ($this->required_capital <= 0) {
            return 0;
        }

        return min(100, ($this->total_invested / $this->required_capital) * 100);
    }

    public function getIsFullyFundedAttribute(): bool
    {
        return $this->total_invested >= $this->required_capital;
    }
}
