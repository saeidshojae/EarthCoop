<?php

namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class HoldingReservation extends Model
{
    public const RESERVED='reserved';
    public const RELEASED='released';
    public const SETTLED='settled';

    protected $table='stock_holding_reservations';
    protected $guarded=[];
    protected $casts=[
        'quantity'=>'integer','settled_quantity'=>'integer','released_quantity'=>'integer',
        'metadata'=>'array','reserved_at'=>'datetime','released_at'=>'datetime','settled_at'=>'datetime',
    ];

    public function holding(){ return $this->belongsTo(Holding::class); }
}
