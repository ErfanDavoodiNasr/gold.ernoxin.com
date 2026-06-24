<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class LastFetch
{
    public function __construct(
        public string $status,
        public int $itemsCount = 0,
        public ?Carbon $startedAt = null,
        public ?Carbon $finishedAt = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'items_count' => $this->itemsCount,
            'started_at' => $this->startedAt?->toIso8601String(),
            'finished_at' => $this->finishedAt?->toIso8601String(),
        ];
    }
}
