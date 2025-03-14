<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['group_type', 'name', 'category', 'location_id', 'description'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user')->withPivot('role')->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }
}