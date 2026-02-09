<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    use HasFactory;
    protected $fillable = ['message', 'title', 'parent_id'];
    
    public function term(){
        return $this->belongsTo(Term::class, 'parent_id');
    }
    
      public function childs(){
        return $this->hasMany(Term::class, 'parent_id');
    }
}
