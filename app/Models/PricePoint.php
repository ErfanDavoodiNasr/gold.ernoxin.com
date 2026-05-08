<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricePoint extends Model
{
    protected $fillable = [
        'market_item_id',
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

    public function item()
    {
        return $this->belongsTo(MarketItem::class, 'market_item_id');
    }
}
