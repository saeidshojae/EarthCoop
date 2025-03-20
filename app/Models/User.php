<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;
    
    protected $fillable = [
        'email', 'phone', 'password', 'fingerprint_id',
        'first_name', 'last_name', 'birth_date', 'gender', 'nationality', 'national_id',
        'invitation_code'
    ];

    protected $hidden = ['password'];

    // روابط چند به چند:
    public function occupationalFields()
    {
        return $this->belongsToMany(OccupationalField::class, 'user_occupational_field');
    }

    public function experienceFields()
    {
        return $this->belongsToMany(ExperienceField::class, 'user_experience_field');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'user_location');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_user')->withPivot('role')->withTimestamps();
    }
}