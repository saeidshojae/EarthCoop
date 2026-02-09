<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryGroupSetting extends Model
{
    use HasFactory;
    
    public $table = 'category_group_setting';
    protected $fillable = ['category_id', 'group_setting_id'];
}
