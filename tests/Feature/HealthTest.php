<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok_json(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'guidely-api',
                'version' => 'v1',
            ]);
    }

    public function test_unknown_api_route_returns_json_not_html_shell(): void
    {
        $response = $this->get('/api/v1/does-not-exist');

        $response->assertNotFound();
        $this->assertTrue(
            str_contains($response->headers->get('content-type', ''), 'application/json'),
            'Unknown API routes must respond with JSON, not the Blade SPA shell'
        );
        $response->assertJsonStructure(['message']);
        $this->assertStringNotContainsString('<div id="app">', $response->getContent());
        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getContent());
    }
}
