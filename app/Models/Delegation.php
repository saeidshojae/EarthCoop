<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delegation extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['poll_id', 'expert_id', 'user_id'];
}
