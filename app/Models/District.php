<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    protected $fillable = ['name_en', 'name_local', 'county_id'];

    public function county()
    {
        return $this->belongsTo(County::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }
}