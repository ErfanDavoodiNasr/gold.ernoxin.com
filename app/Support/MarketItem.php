<?php

namespace App\Support;

use App\Models\PricePoint;

class MarketItem
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public string $category,
        public ?string $currency,
        public ?PricePoint $latestPrice = null,
    ) {
    }

    public function isUsd(): bool
    {
        return str_contains($this->name, 'انس') || strtoupper((string)$this->currency) === 'USD' || $this->currency === '$';
    }
}
