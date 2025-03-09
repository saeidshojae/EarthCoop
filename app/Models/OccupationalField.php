<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OccupationalField extends Model
{
    protected $fillable = ['name', 'parent_id'];

    public function parent()
    {
        return $this->belongsTo(OccupationalField::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(OccupationalField::class, 'parent_id');
    }
}
