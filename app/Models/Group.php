<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    protected $fillable = ['group_type', 'name', 'location_level', 'is_open', 'address_id', 'specialty_id', 'experience_id', 'age_group_id', 'gender', 'age_group_title', 'description', 'avatar', 'last_activity_at'];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user')->withPivot('role')->withTimestamps();
    }


    public function specialty()
    {
        return $this->belongsTo(OccupationalField::class, 'specialty_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function experience()
    {
        return $this->belongsTo(ExperienceField::class, 'experience_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function ageGroup(){
        return $this->belongsTo(AgeGroup::class, 'age_group_id');
    }

    public function gender(){
        return $this->gender == 'male' ? 'مرد' : 'زن';
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function userCount(){
        return $this->hasMany(GroupUser::class)->where('status', 1)->where('role', '!=', 4)->count();
    }
    public function guestsCount(){
        return $this->hasMany(GroupUser::class)->where('status', 1)->where('role', 4)->count();
    }

    public function groupUser(){
        return $this->hasMany(GroupUser::class);
    }

    public function polls(){
        return $this->hasMany(Poll::class);
    }

    public function blogs(){
        return $this->hasMany(Blog::class, 'group_id');
    }

    public function elections(){
        return $this->hasMany(Election::class, 'group_id');
    }

    public function updateLastActivity()
    {
        $this->update(['last_activity_at' => now()]);
    }
}

