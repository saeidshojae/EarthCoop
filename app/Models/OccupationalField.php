<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OccupationalField extends Model
{
    protected $fillable = ['name', 'parent_id', 'status'];

    public function parent()
    {
        return $this->belongsTo(OccupationalField::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(OccupationalField::class, 'parent_id');
    }

    public function getDepthAttribute()
    {
        $depth = 0;
        $node  = $this;
        // تا وقتی والد دارد، یک سطح بالا می‌رویم
        while ($node->parent) {
            $depth++;
            $node = $node->parent;
        }
        return $depth;
    }

}
