<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_api_requests_are_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'method' => 'GET',
            'path' => '/api/auth/me',
            'status_code' => 200,
        ]);
    }

    public function test_activity_log_endpoint_only_returns_the_authenticated_users_logs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $activityLog = ActivityLog::create([
            'user_id' => $user->id,
            'method' => 'POST',
            'path' => '/api/transactions',
            'status_code' => 201,
        ]);

        ActivityLog::create([
            'user_id' => $otherUser->id,
            'method' => 'DELETE',
            'path' => '/api/accounts/1',
            'status_code' => 200,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/activity-logs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $activityLog->id)
            ->assertJsonPath('data.data.0.path', '/api/transactions')
            ->assertJsonPath('data.total', 1);
    }

    public function test_unauthenticated_requests_are_not_recorded(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();

        $this->assertDatabaseCount('activity_logs', 0);
    }
}
