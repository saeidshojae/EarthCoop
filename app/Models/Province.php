<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;

    protected $fillable = ['name_en', 'name_local', 'country_id'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function counties()
    {
        return $this->hasMany(County::class);
    }
}