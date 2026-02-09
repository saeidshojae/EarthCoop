<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NajmBaharAgreement extends Model
{
    use HasFactory;
    
    protected $fillable = ['title', 'content', 'parent_id', 'order'];
    
    protected $casts = [
        'order' => 'integer',
    ];
    
    /**
     * Get the parent agreement
     */
    public function parent()
    {
        return $this->belongsTo(NajmBaharAgreement::class, 'parent_id');
    }
    
    /**
     * Get child agreements
     */
    public function children()
    {
        return $this->hasMany(NajmBaharAgreement::class, 'parent_id')->orderBy('order');
    }
    
    /**
     * Get all descendants recursively
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }
}
