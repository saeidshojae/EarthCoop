<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionProcessReviewEndorsement extends Model
{
    public $timestamps = false;

    protected $fillable = ['review_id', 'user_id', 'endorsed_at'];
    protected $casts = ['endorsed_at' => 'datetime'];

    public function review() { return $this->belongsTo(ElectionProcessReview::class, 'review_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
