<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alley extends Model
{
    use HasFactory;
    protected $table = 'alleies';
    protected $fillable = ['name', 'parent_id', 'status']; // فیلدهای قابل مقداردهی

}
