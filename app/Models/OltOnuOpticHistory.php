<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OltOnuOpticHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'olt_onu_optic_id',
        'olt_connection_id',
        'owner_id',
        'rx_onu_dbm',
        'tx_onu_dbm',
        'rx_olt_dbm',
        'distance_m',
        'status',
        'polled_at',
    ];

    protected $casts = [
        'polled_at'   => 'datetime',
        'rx_onu_dbm'  => 'float',
        'tx_onu_dbm'  => 'float',
        'rx_olt_dbm'  => 'float',
        'distance_m'  => 'integer',
    ];

    public function onuOptic(): BelongsTo
    {
        return $this->belongsTo(OltOnuOptic::class, 'olt_onu_optic_id');
    }

    public function oltConnection(): BelongsTo
    {
        return $this->belongsTo(OltConnection::class);
    }
}
