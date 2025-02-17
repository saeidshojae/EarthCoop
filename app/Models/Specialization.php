<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialization extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'parent_id', 'industrial_field_id', 'level'];

    public function industrialField()
    {
        return $this->belongsTo(IndustrialField::class);
    }

    public function parent()
    {
        return $this->belongsTo(Specialization::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Specialization::class, 'parent_id');
    }
}