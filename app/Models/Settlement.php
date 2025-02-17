<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = ['name_en', 'name_local', 'district_id'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function localities()
    {
        return $this->hasMany(Locality::class);
    }
}