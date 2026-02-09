<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Village extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'rural_id', 'district_id', 'status'];

    public function rural()
    {
        return $this->belongsTo(Rural::class);
    }
}
