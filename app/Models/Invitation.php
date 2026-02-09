<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasFactory;
    protected $table ='invitations';
    protected $fillable = ['email', 'message', 'job', 'code', 'status', 'reviewed_at', 'reviewed_by', 'admin_note'];
    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeStatus($query, $status)
    {
        if (!empty($status)) {
            $query->where('status', $status);
        }
        return $query;
    }
}

