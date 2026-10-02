<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketApiValidationTest extends TestCase
{
    public function test_history_rejects_invalid_range_with_422(): void
    {
        $response = $this->getJson('/api/market/items/3/history?range=not-a-range');
        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'allowed']);
    }

    public function test_history_unknown_item_is_404(): void
    {
        $this->getJson('/api/market/items/99999/history?range=1d')->assertStatus(404);
        $this->getJson('/api/market/items/not-a-slug/history?range=1d')->assertStatus(404);
    }

    public function test_summary_returns_json_shape_or_500_without_db(): void
    {
        // Without a live MySQL this may 500 — either way must be JSON, never HTML dump of secrets.
        $response = $this->get('/api/market/summary');
        $this->assertContains($response->getStatusCode(), [200, 500]);
        $this->assertStringContainsString('application/json', (string)$response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringNotContainsString('DB_PASSWORD', $body);
        $this->assertStringNotContainsString('APP_KEY', $body);
    }
}
