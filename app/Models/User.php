<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'nationality',
        'national_id',
        'phone',
        'email',
        'birth_date',
        'gender',
        'password',
        'location',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
    ];

    public function jobFields()
    {
        return $this->belongsToMany(jobField::class, 'user_job_fields');
    }

    public function specializations()
    {
        return $this->belongsToMany(Specialization::class, 'user_specializations');
    }
}