<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndustrialField extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'parent_id',
        'level'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_industrial_fields');
    }

    public function parent()
    {
        return $this->belongsTo(IndustrialField::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(IndustrialField::class, 'parent_id');
    }

    public function specializations()
    {
        return $this->hasMany(Specialization::class);
    }
}