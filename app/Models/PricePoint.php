<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PricePoint extends Model
{
    protected $fillable = [
        'item_key',
        'current_value',
        'high_value',
        'low_value',
        'yesterday_avg_value',
        'change_value',
        'change_percent',
        'direction',
        'raw_payload',
        'fetched_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'fetched_at' => 'datetime',
        'current_value' => 'float',
        'high_value' => 'float',
        'low_value' => 'float',
        'yesterday_avg_value' => 'float',
        'change_value' => 'float',
        'change_percent' => 'float',
    ];

    protected static function booted()
    {
        static::creating(function (self $pricePoint) {
            if (config('database.default') === 'pgsql' && !$pricePoint->getKey()) {
                $pricePoint->id = (string)Str::uuid();
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
