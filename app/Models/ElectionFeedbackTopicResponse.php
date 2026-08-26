<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionFeedbackTopicResponse extends Model
{
    protected $fillable = [
        'election_id', 'subject_user_id', 'topic_key', 'aggregation_window_start',
        'aggregation_window_end', 'aggregate_count', 'min_distinct_authors',
        'min_bucket_days', 'body', 'status', 'published_at',
    ];

    protected $casts = [
        'aggregation_window_start' => 'date',
        'aggregation_window_end' => 'date',
        'published_at' => 'datetime',
    ];

    public function election() { return $this->belongsTo(Election::class); }
    public function subject() { return $this->belongsTo(User::class, 'subject_user_id'); }
}
