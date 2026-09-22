<?php

namespace App\Http\Controllers;

use App\Models\TaskCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = TaskCategory::query()
            ->when(
                $request->boolean('active_only'),
                fn ($query) => $query->where('is_active', true)
            )
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Task categories retrieved successfully.',
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TaskCategory::class);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:task_categories,name',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'color' => [
                'nullable',
                'string',
                'max:20',
            ],

            'is_active' => [
                'boolean',
            ],
        ]);

        $category = TaskCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Task category created successfully.',
            'data' => $category,
        ], 201);
    }

    public function show(TaskCategory $taskCategory): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $taskCategory,
        ]);
    }

    public function update(
        Request $request,
        TaskCategory $taskCategory
    ): JsonResponse {
        $this->authorize('update', $taskCategory);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('task_categories', 'name')
                    ->ignore($taskCategory->id),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'color' => [
                'nullable',
                'string',
                'max:20',
            ],

            'is_active' => [
                'boolean',
            ],
        ]);

        $taskCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Task category updated successfully.',
            'data' => $taskCategory->fresh(),
        ]);
    }

    public function destroy(
        TaskCategory $taskCategory
    ): JsonResponse {
        $this->authorize('delete', $taskCategory);

        $taskCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task category deleted successfully.',
        ]);
    }
}