<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Spring extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'user_id', 'amount', 'status', 'cart_number'];
    
    public function user(){
        return $this->belongsTo(User::class);
    }
}
