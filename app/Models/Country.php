<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = ['name_en', 'name_local', 'code', 'continent_id'];

    public function continent()
    {
        return $this->belongsTo(Continent::class);
    }

    public function provinces()
    {
        return $this->hasMany(Province::class);
    }
}