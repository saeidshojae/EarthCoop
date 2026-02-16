<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NajmBaharProjectInvestment extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'amount',
        'note',
        'status',
        'decision_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(NajmBaharProject::class, 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
