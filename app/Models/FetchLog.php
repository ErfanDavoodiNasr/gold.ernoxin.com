<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FetchLog extends Model
{
    protected $fillable = ['source', 'status', 'http_status', 'items_count', 'message', 'reference_id', 'started_at', 'finished_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
