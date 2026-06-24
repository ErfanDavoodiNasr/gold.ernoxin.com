<?php

namespace App\Services;

class AutoPriceFetcher
{
    public function __construct(
        private PriceIngestor    $ingestor,
        private FetchStatusStore $fetchStatus,
    )
    {
    }

    public function fetchIfDue(bool $force = false): array
    {
        if (!$force && !$this->isDue()) {
            return ['status' => 'skipped'];
        }

        try {
            $result = $this->ingestor->fetchAndStore();

            return array_merge(['status' => 'success'], $result);
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function isDue(): bool
    {
        $interval = max(1, (int)config('gold.fetch_interval_minutes', 5));
        $now = now();

        if ($now->minute % $interval !== 0) {
            return false;
        }

        $slotStart = $now->copy()->second(0);
        $lastSuccess = $this->fetchStatus->lastSuccessStartedAt();

        if ($lastSuccess && $lastSuccess->gte($slotStart)) {
            return false;
        }

        return true;
    }
}
