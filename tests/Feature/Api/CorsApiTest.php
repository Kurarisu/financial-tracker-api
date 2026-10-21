<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CorsApiTest extends TestCase
{
    public function test_vite_origin_is_allowed_to_make_api_preflight_requests(): void
    {
        $response = $this->call('OPTIONS', '/api/ping', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}