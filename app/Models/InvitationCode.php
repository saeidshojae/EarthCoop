<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvitationCode extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'user_id', 'used', 'used_by', 'expire_at', 'used_at'];
    
    protected $casts = [
        'expire_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    public function usedBy(){
        return $this->belongsTo(User::class, 'used_by');
    }
    
    public function user(){
        return $this->belongsTo(User::class);
    }
}