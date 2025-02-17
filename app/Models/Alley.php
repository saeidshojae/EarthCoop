<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alley extends Model
{
    use HasFactory;

    protected $fillable = ['name_en', 'name_local', 'street_id'];

    public function street()
    {
        return $this->belongsTo(Street::class);
    }
}