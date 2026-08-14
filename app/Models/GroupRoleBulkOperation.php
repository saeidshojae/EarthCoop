<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupRoleBulkOperation extends Model
{
    protected $fillable = [
        'created_by', 'filters', 'source_role', 'target_role', 'duration_unit',
        'duration_value', 'status', 'total_items', 'processed_items', 'applied_items',
        'cancelled_items', 'skipped_items', 'failed_items', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(GroupRoleBulkOperationItem::class, 'operation_id');
    }
}
