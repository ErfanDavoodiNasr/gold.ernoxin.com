<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarketItem extends Model
{
    protected $fillable = ['source', 'category', 'name', 'normalized_name', 'currency', 'is_active', 'meta'];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function (self $item) {
            if (config('database.default') === 'pgsql' && !$item->getKey()) {
                $item->id = (string)Str::uuid();
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

    public function prices()
    {
        return $this->hasMany(PricePoint::class);
    }

    public function latestPrice()
    {
        return $this->hasOne(PricePoint::class)->latestOfMany('fetched_at');
    }
}
