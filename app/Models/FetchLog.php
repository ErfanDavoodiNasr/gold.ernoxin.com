<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FetchLog extends Model
{
    protected $fillable = ['source', 'status', 'http_status', 'items_count', 'message', 'reference_id', 'started_at', 'finished_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (self $fetchLog) {
            if (config('database.default') === 'pgsql' && !$fetchLog->getKey()) {
                $fetchLog->id = (string)Str::uuid();
            }
        });
    }

    public function getIncrementing()
    {
        return config('database.default') !== 'pgsql';
    }

    public function getKeyType()
    {
        return config('database.default') === 'pgsql' ? 'string' : 'int';
    }
}
