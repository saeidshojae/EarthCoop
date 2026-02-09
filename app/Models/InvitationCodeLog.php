<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvitationCodeLog extends Model
{
    use HasFactory;

    protected $fillable = ['invitation_code_id', 'action', 'actor_id', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function code()
    {
        return $this->belongsTo(InvitationCode::class, 'invitation_code_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}


