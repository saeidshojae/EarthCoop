<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;
    protected $fillable = ['election_id', 'user_id', 'accept_status'];

    public function votes()
    {
        // فرض می‌کنیم ستون foreign key در جدول votes به نام candidate_id باشد
        return $this->hasMany(Vote::class, 'candidate_id');
    }
    public function user()
    {
        // فرض می‌کنیم ستون foreign key در جدول votes به نام candidate_id باشد
        return $this->belongsTo(User::class);
    }

    public function election()
    {
        return $this->belongsTo(Election::class);
    }

}
