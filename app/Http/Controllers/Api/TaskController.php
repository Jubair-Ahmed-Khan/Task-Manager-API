<?php

namespace App\Http\Controllers\Api;

use App\Models\Task;
use App\Models\TaskCategory;
use Illuminate\Http\Request;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\TaskActivityService;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;



class TaskController extends Controller
{

    //index method to list all tasks with filters and pagination
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', Task::class);
        $tasks = Task::query()
            ->visibleTo($user)
            ->with([
                'assignee:id,name,email',
                'user:id,name,email',
                'category:id,name,color',
            ])
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request->search;
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where(
                                'title',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $request->filled('status'),
                function ($query) use ($request) {
                    $query->where(
                        'status',
                        $request->status
                    );

                }
            )
            ->when(
                $request->filled('priority'),
                function ($query) use ($request) {
                    $query->where(
                        'priority',
                        $request->priority
                    );

                }
            )
            ->when(
                $request->filled('category_id'),
                function ($query) use ($request) {
                    $query->where('category_id', $request->category_id);
                }
            )
            ->when(
                $request->filled('due_status'),
                function ($query) use ($request) {

                    if ($request->due_status === 'overdue') {

                        $query
                            ->whereNotNull('due_date')
                            ->whereDate('due_date', '<', today())
                            ->where('status', '!=', 'completed');

                    }

                    if ($request->due_status === 'due_soon') {

                        $query
                            ->whereNotNull('due_date')
                            ->whereBetween(
                                'due_date',
                                [
                                    today(),
                                    today()->copy()->addDays(3),
                                ]
                            )
                            ->where('status', '!=', 'completed');

                    }

                }
            )
            ->when(
                $user->hasRole('Admin')
                && $request->filled('assigned_to'),
                function ($query) use ($request) {
                    $query->where(
                        'assigned_to',
                        $request->assigned_to
                    );

                }
            )
            ->when(
            $request->boolean('overdue'),
                function ($query) {

                    $query
                        ->whereNotNull('due_date')
                        ->whereDate(
                            'due_date',
                            '<',
                            now()->toDateString()
                        )
                        ->where(
                            'status',
                            '!=',
                            'completed'
                        );
                }
            )
            ->latest()
            ->paginate(
                $request->integer(
                    'per_page',
                    10
                )
            );

        return response()->json([
            'success' => true,
            'message' => 'Tasks retrieved successfully.',
            'data' => $tasks,
        ]);
    }

    // Store method to create a new task
    // public function store(StoreTaskRequest $request, TaskService $taskService): JsonResponse 
    // {
    //     $this->authorize('create', Task::class);
    //     $task = $taskService->createTask($request->validated(), $request->user());

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Task created successfully.',
    //         'data' => $task,
    //     ], 201);
    // }
    public function store(
        StoreTaskRequest $request,
        TaskService $taskService,
        TaskActivityService $activityService
    ): JsonResponse {
        $this->authorize('create', Task::class);

        $task = $taskService->createTask(
            $request->validated(),
            $request->user()
        );

        $activityService->created(
            $task,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'data' => $task,
        ], 201);
    }

    // Show method to retrieve a specific task
    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load([
            'assignee:id,name,email',
            'user:id,name,email',
            'category:id,name,color',
            'comments.user:id,name,email',
            'attachments.user:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task retrieved successfully.',
            'data' => $task,
        ]);
    }

    // update method to update a specific task
    // public function update(UpdateTaskRequest $request, Task $task): JsonResponse 
    // {

    //     $this->authorize('update', $task);
    //     $task->update($request->validated());
    //     $task->load('assignee:id,name,email');

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Task updated successfully.',
    //         'data' => $task,
    //     ]);
    // }
    public function update(
        UpdateTaskRequest $request,
        Task $task,
        TaskService $taskService,
        TaskActivityService $activityService
    ): JsonResponse {
        $this->authorize('update', $task);

        $user = $request->user();

        $oldStatus = $task->status;
        $oldPriority = $task->priority;
        $oldAssignedTo = $task->assigned_to;
        $oldCategoryId = $task->category_id;

        $validated = $request->validated();

        $task = $taskService->updateTask(
            $task,
            $validated
        );

        /*
        |--------------------------------------------------------------------------
        | General task update
        |--------------------------------------------------------------------------
        */

        $generalChanges = collect([
            'title',
            'description',
            'due_date',
        ])->contains(
            fn ($field) => array_key_exists($field, $validated)
                && $task->wasChanged($field)
        );

        if ($generalChanges) {
            $activityService->updated(
                $task,
                $user
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Status changed
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists('status', $validated)
            && $oldStatus !== $task->status
        ) {
            $activityService->statusChanged(
                $task,
                $user,
                $oldStatus,
                $task->status
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Priority changed
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists('category_id', $validated)
            && $oldCategoryId !== $task->category_id
        ) {
            $oldCategoryName = $oldCategoryId
                ? TaskCategory::find($oldCategoryId)?->name
                : null;

            $newCategoryName = $task->category?->name;

            $activityService->categoryChanged(
                $task,
                $user,
                $oldCategoryName,
                $newCategoryName
            );
        }

        if (
            array_key_exists('priority', $validated)
            && $oldPriority !== $task->priority
        ) {
            $activityService->priorityChanged(
                $task,
                $user,
                $oldPriority,
                $task->priority
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Employee assignment changed
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists('assigned_to', $validated)
            && $oldAssignedTo !== $task->assigned_to
        ) {
            $employeeName = $task->assignee?->name;

            $activityService->assigned(
                $task,
                $user,
                $employeeName
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully.',
            'data' => $task,
        ]);
    }

    // Update status method to update the status of a specific task
    // public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse 
    // {

    //     $this->authorize('updateStatus', $task);
    //     $task->update([
    //         'status' => $request->validated('status'),
    //     ]);
    //     $task->load('assignee:id,name,email');

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Task status updated successfully.',
    //         'data' => $task,
    //     ]);
    // }
    public function updateStatus(
        UpdateTaskStatusRequest $request,
        Task $task,
        TaskService $taskService,
        TaskActivityService $activityService
    ): JsonResponse {
        $this->authorize('updateStatus', $task);

        $user = $request->user();

        $oldStatus = $task->status;

        $task = $taskService->updateStatus(
            $task,
            $request->validated()['status']
        );

        if ($oldStatus !== $task->status) {
            $activityService->statusChanged(
                $task,
                $user,
                $oldStatus,
                $task->status
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully.',
            'data' => $task,
        ]);
    }

    //delete method to delete a specific task
    public function destroy(Request $request, Task $task): JsonResponse 
    {

        $this->authorize('delete', $task);
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully.',
        ]);
    }
}