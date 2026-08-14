<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupRoleBulkOperationItem extends Model
{
    protected $fillable = ['operation_id', 'membership_id', 'group_id', 'user_id', 'status', 'result', 'error'];

    public function operation()
    {
        return $this->belongsTo(GroupRoleBulkOperation::class, 'operation_id');
    }

    public function membership()
    {
        return $this->belongsTo(GroupUser::class, 'membership_id');
    }
}
