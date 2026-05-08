<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketItem extends Model
{
    protected $fillable = ['source', 'category', 'name', 'normalized_name', 'currency', 'is_active', 'meta'];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function prices()
    {
        return $this->hasMany(PricePoint::class);
    }

    public function latestPrice()
    {
        return $this->hasOne(PricePoint::class)->latestOfMany('fetched_at');
    }
}
