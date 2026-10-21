<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks.
     * GET /api/tasks
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()->latest();

        // Optional filter by completion status: ?completed=true or ?completed=false
        if ($request->has('completed')) {
            $query->where('is_completed', filter_var($request->query('completed'), FILTER_VALIDATE_BOOLEAN));
        }

        $tasks = $query->get();

        return response()->json([
            'status' => 'success',
            'count' => $tasks->count(),
            'data' => $tasks,
        ], 200);
    }

    /**
     * Store a newly created task.
     * POST /api/tasks
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_completed' => ['nullable', 'boolean'],
        ]);

        $task = Task::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_completed' => $validated['is_completed'] ?? false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Task created successfully',
            'data' => $task,
        ], 201);
    }

    /**
     * Display the specified task.
     * GET /api/tasks/{id}
     */
    public function show(Task $task): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $task,
        ], 200);
    }

    /**
     * Update the specified task.
     * PUT/PATCH /api/tasks/{id}
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        $task->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Task updated successfully',
            'data' => $task,
        ], 200);
    }

    /**
     * Remove the specified task.
     * DELETE /api/tasks/{id}
     */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Task deleted successfully',
        ], 200);
    }
}
