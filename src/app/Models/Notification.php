<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'batch_id',
        'recipient_id',
        'channel',
        'text',
        'priority',
        'status',
        'retry_count',
        'last_error',
        'provider_response',
        'sent_at',
        'delivered_at'
    ];

    protected $casts = [
        'provider_response' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime'
    ];
}