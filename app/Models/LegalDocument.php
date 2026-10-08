<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    protected $fillable = ['slug', 'title', 'source_type', 'source_root_id'];

    public function versions()
    {
        return $this->hasMany(LegalDocumentVersion::class);
    }
}
