<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupUser extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'group_user';
    protected $fillable = ['group_id', 'user_id', 'role', 'status', 'expired', 'last_read_message_id'];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function group(){
        return $this->belongsTo(Group::class);
    }
}
