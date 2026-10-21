<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_ping_returns_ok(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'ok');
    }

    public function test_can_list_tasks(): void
    {
        Task::create(['title' => 'Test Task 1', 'is_completed' => false]);
        Task::create(['title' => 'Test Task 2', 'is_completed' => true]);

        $response = $this->getJson('/api/tasks');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('status', 'success');
    }

    public function test_can_filter_tasks_by_completed(): void
    {
        Task::create(['title' => 'Pending Task', 'is_completed' => false]);
        Task::create(['title' => 'Done Task', 'is_completed' => true]);

        $response = $this->getJson('/api/tasks?completed=true');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.title', 'Done Task');
    }

    public function test_can_create_a_task(): void
    {
        $payload = [
            'title' => 'New Task via API',
            'description' => 'Testing task creation',
            'is_completed' => false,
        ];

        $response = $this->postJson('/api/tasks', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.title', 'New Task via API');

        $this->assertDatabaseHas('tasks', ['title' => 'New Task via API']);
    }

    public function test_validation_error_when_title_is_missing(): void
    {
        $response = $this->postJson('/api/tasks', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['title']);
    }

    public function test_can_show_a_task(): void
    {
        $task = Task::create(['title' => 'Show me', 'description' => 'Detail']);

        $response = $this->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $task->id);
    }

    public function test_can_update_a_task(): void
    {
        $task = Task::create(['title' => 'Initial Title', 'is_completed' => false]);

        $response = $this->putJson("/api/tasks/{$task->id}", [
            'title' => 'Updated Title',
            'is_completed' => true,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.title', 'Updated Title')
                 ->assertJsonPath('data.is_completed', true);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
            'is_completed' => 1,
        ]);
    }

    public function test_can_delete_a_task(): void
    {
        $task = Task::create(['title' => 'Delete me']);

        $response = $this->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
