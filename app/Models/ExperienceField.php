<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienceField extends Model
{
    protected $fillable = ['name', 'parent_id', 'status'];

    public function parent()
    {
        return $this->belongsTo(ExperienceField::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ExperienceField::class, 'parent_id');
    }
}
