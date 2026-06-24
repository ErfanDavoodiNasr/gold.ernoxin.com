<?php

namespace App\Services;

use App\Models\PricePoint;
use App\Support\MarketItem;
use Illuminate\Support\Collection;

class MarketCatalog
{
    /** @var array<int, array{id:int,key:string,name:string,category:string,currency:?string}>|null */
    private ?array $definitions = null;

    public function find(int $id): ?MarketItem
    {
        $definition = $this->definitions()[$id] ?? null;

        return $definition ? $this->makeItem($definition) : null;
    }

    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = [];
        $id = 1;

        foreach (['gold', 'coin'] as $category) {
            foreach (config("gold.known_items.{$category}", []) as $name) {
                $key = PersianNumber::label($name);
                $definitions[$id] = [
                    'id' => $id,
                    'key' => $key,
                    'name' => $name,
                    'category' => $category,
                    'currency' => $key === 'انس طلا' ? '$' : null,
                ];
                $id++;
            }
        }

        return $this->definitions = $definitions;
    }

    private function makeItem(array $definition, ?PricePoint $latestPrice = null): MarketItem
    {
        return new MarketItem(
            id: $definition['id'],
            key: $definition['key'],
            name: $definition['name'],
            category: $definition['category'],
            currency: $definition['currency'],
            latestPrice: $latestPrice,
        );
    }

    public function findByKey(string $key): ?MarketItem
    {
        $normalized = PersianNumber::label($key);

        foreach ($this->definitions() as $definition) {
            if ($definition['key'] === $normalized) {
                return $this->makeItem($definition);
            }
        }

        return null;
    }

    public function allWithLatestPrices(): Collection
    {
        $latestByKey = $this->latestPricesByKey();

        return collect($this->definitions())
            ->map(fn(array $definition) => $this->makeItem($definition, $latestByKey->get($definition['key'])));
    }

    /** @return Collection<string, PricePoint> */
    private function latestPricesByKey(): Collection
    {
        $keys = $this->keys();
        if ($keys === []) {
            return collect();
        }

        $latest = PricePoint::query()
            ->selectRaw('item_key, MAX(fetched_at) as max_fetched_at')
            ->whereIn('item_key', $keys)
            ->where('current_value', '>', 0)
            ->groupBy('item_key');

        return PricePoint::query()
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('price_points.item_key', '=', 'latest.item_key')
                    ->on('price_points.fetched_at', '=', 'latest.max_fetched_at');
            })
            ->whereIn('price_points.item_key', $keys)
            ->where('price_points.current_value', '>', 0)
            ->get()
            ->keyBy('item_key');
    }

    public function keys(): array
    {
        return array_column($this->definitions(), 'key');
    }
}
