<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['group_type', 'name', 'category', 'location_id'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user');
    }
}
