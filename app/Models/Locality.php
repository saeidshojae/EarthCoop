<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Locality extends Model
{
    use HasFactory;

    protected $fillable = ['name_en', 'name_local', 'settlement_id'];

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    public function neighborhoods()
    {
        return $this->hasMany(Neighborhood::class);
    }
}