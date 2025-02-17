<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Neighborhood extends Model
{
    use HasFactory;

    protected $fillable = ['name_en', 'name_local', 'locality_id'];

    public function locality()
    {
        return $this->belongsTo(Locality::class);
    }

    public function streets()
    {
        return $this->hasMany(Street::class);
    }
}