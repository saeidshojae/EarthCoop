<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Street extends Model
{
    use HasFactory;

    protected $fillable = ['name_en', 'name_local', 'neighborhood_id'];

    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function alleys()
    {
        return $this->hasMany(Alley::class);
    }
}