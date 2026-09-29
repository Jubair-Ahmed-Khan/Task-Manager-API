<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Notifications\TaskNotification;
use App\Services\TaskActivityService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * List tasks with filters, pagination,
     * estimated time, and tracked time.
     */
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

            // Total saved tracking time for each task.
            ->withSum(
                'timeEntries',
                'duration_seconds'
            )

            // Search by task title or description.
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request->search;

                    $query->where(function ($query) use ($search) {
                        $query->where(
                            'title',
                            'like',
                            "%{$search}%"
                        )->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        );
                    });
                }
            )

            // Filter by status.
            ->when(
                $request->filled('status'),
                function ($query) use ($request) {
                    $query->where(
                        'status',
                        $request->status
                    );
                }
            )

            // Filter by priority.
            ->when(
                $request->filled('priority'),
                function ($query) use ($request) {
                    $query->where(
                        'priority',
                        $request->priority
                    );
                }
            )

            // Filter by category.
            ->when(
                $request->filled('category_id'),
                function ($query) use ($request) {
                    $query->where(
                        'category_id',
                        $request->category_id
                    );
                }
            )

            // Filter by due date.
            ->when(
                $request->filled('due_status'),
                function ($query) use ($request) {

                    if ($request->due_status === 'overdue') {
                        $query->whereNotNull('due_date')
                            ->whereDate(
                                'due_date',
                                '<',
                                today()
                            )
                            ->where(
                                'status',
                                '!=',
                                'completed'
                            );
                    }

                    if ($request->due_status === 'due_soon') {
                        $query->whereNotNull('due_date')
                            ->whereBetween('due_date', [
                                today(),
                                today()->copy()->addDays(3),
                            ])
                            ->where(
                                'status',
                                '!=',
                                'completed'
                            );
                    }
                }
            )

            // Admin can filter tasks by assigned employee.
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

            // Backward-compatible overdue filter.
            ->when(
                $request->boolean('overdue'),
                function ($query) {
                    $query->whereNotNull('due_date')
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
                $request->integer('per_page', 10)
            );

        return response()->json([
            'success' => true,
            'message' => 'Tasks retrieved successfully.',
            'data' => $tasks,
        ]);
    }

    /**
     * Create a task.
     *
     * Notify the assigned employee when a task is created.
     */
    public function store(
        StoreTaskRequest $request,
        TaskService $taskService,
        TaskActivityService $activityService
    ): JsonResponse {

        $this->authorize('create', Task::class);

        $user = $request->user();

        $task = $taskService->createTask(
            $request->validated(),
            $user
        );

        // Record task creation activity.
        $activityService->created(
            $task,
            $user
        );

        // Notify assigned employee.
        if ($task->assigned_to) {

            $assignee = $task->assignee;

            if (
                $assignee
                && $assignee->id !== $user->id
            ) {

                $message = $user->name
                    . ' assigned you a task: '
                    . $task->title;

                $this->sendTaskNotification(
                    $assignee,
                    $task,
                    $user,
                    'task_assigned',
                    $message
                );
            }
        }

        // Load task relationships.
        $task->load([
            'assignee:id,name,email',
            'user:id,name,email',
            'category:id,name,color',
        ]);

        // Include total tracked seconds.
        $task->loadSum(
            'timeEntries',
            'duration_seconds'
        );

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'data' => $task,
        ], 201);
    }

    /**
     * Retrieve a specific task with time-tracking information.
     */
    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load([
            'assignee:id,name,email',
            'user:id,name,email',
            'category:id,name,color',
            'comments.user:id,name,email',
            'attachments.user:id,name,email',

            // Time entries and their associated users.
            'timeEntries.user:id,name,email',
        ]);

        // Include total tracked seconds.
        $task->loadSum(
            'timeEntries',
            'duration_seconds'
        );

        return response()->json([
            'success' => true,
            'message' => 'Task retrieved successfully.',
            'data' => $task,
        ]);
    }

    /**
     * Update a task.
     *
     * Notifications:
     * - Notify the new employee when assigned or reassigned.
     * - Record task field changes in activity history.
     */
    public function update(
        UpdateTaskRequest $request,
        Task $task,
        TaskService $taskService,
        TaskActivityService $activityService
    ): JsonResponse {

        $this->authorize('update', $task);

        $user = $request->user();

        // Capture old values before updating.
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
            fn ($field) =>
                array_key_exists($field, $validated)
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

            // Notify the task creator about status changes.
            $this->notifyTaskCreator(
                $task,
                $user,
                'status_updated',
                $user->name
                    . ' updated the status of: '
                    . $task->title
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Category changed
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists('category_id', $validated)
            && $oldCategoryId != $task->category_id
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

        /*
        |--------------------------------------------------------------------------
        | Priority changed
        |--------------------------------------------------------------------------
        */

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
            && $oldAssignedTo != $task->assigned_to
        ) {

            $employeeName = $task->assignee?->name;

            $activityService->assigned(
                $task,
                $user,
                $employeeName
            );

            // Notify the newly assigned employee.
            if ($task->assigned_to) {

                $assignee = $task->assignee;

                if (
                    $assignee
                    && $assignee->id !== $user->id
                ) {

                    $message = $user->name
                        . ' assigned you a task: '
                        . $task->title;

                    $this->sendTaskNotification(
                        $assignee,
                        $task,
                        $user,
                        'task_assigned',
                        $message
                    );
                }
            }
        }

        // Load updated task relationships.
        $task->load([
            'assignee:id,name,email',
            'user:id,name,email',
            'category:id,name,color',
        ]);

        // Include total tracked seconds.
        $task->loadSum(
            'timeEntries',
            'duration_seconds'
        );

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully.',
            'data' => $task,
        ]);
    }

    /**
     * Update task status.
     *
     * Notify the task creator when the status changes.
     */
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

            // Notify the task creator.
            $this->notifyTaskCreator(
                $task,
                $user,
                'status_updated',
                $user->name
                    . ' updated the status of: '
                    . $task->title
            );
        }

        $task->load([
            'assignee:id,name,email',
            'user:id,name,email',
            'category:id,name,color',
        ]);

        // Include total tracked seconds.
        $task->loadSum(
            'timeEntries',
            'duration_seconds'
        );

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully.',
            'data' => $task,
        ]);
    }

    /**
     * Delete a task.
     */
    public function destroy(
        Request $request,
        Task $task
    ): JsonResponse {

        $this->authorize('delete', $task);

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Notification helper methods
    |--------------------------------------------------------------------------
    */

    /**
     * Send a database notification to a user.
     */
    private function sendTaskNotification(
        $recipient,
        Task $task,
        $actor,
        string $action,
        string $message
    ): void {

        $recipient->notify(
            new TaskNotification(
                $task,
                $actor,
                $action,
                $message
            )
        );
    }

    /**
     * Notify the task creator, excluding the person
     * who performed the action.
     */
    private function notifyTaskCreator(
        Task $task,
        $actor,
        string $action,
        string $message
    ): void {

        $creator = $task->user;

        if (
            $creator
            && $creator->id !== $actor->id
        ) {

            $this->sendTaskNotification(
                $creator,
                $task,
                $actor,
                $action,
                $message
            );
        }
    }
}



// namespace App\Http\Controllers\Api;

// use App\Http\Controllers\Controller;
// use App\Http\Requests\StoreTaskRequest;
// use App\Http\Requests\UpdateTaskRequest;
// use App\Http\Requests\UpdateTaskStatusRequest;
// use App\Models\Task;
// use App\Models\TaskCategory;
// use App\Notifications\TaskNotification;
// use App\Services\TaskActivityService;
// use App\Services\TaskService;
// use Illuminate\Http\JsonResponse;
// use Illuminate\Http\Request;

// class TaskController extends Controller
// {

//     public function index(Request $request): JsonResponse
//     {
//         $user = $request->user();

//         $this->authorize('viewAny', Task::class);

//         $tasks = Task::query()
//             ->visibleTo($user)
//             ->with([
//                 'assignee:id,name,email',
//                 'user:id,name,email',
//                 'category:id,name,color',
//             ])
//             ->when(
//                 $request->filled('search'),
//                 function ($query) use ($request) {
//                     $search = $request->search;

//                     $query->where(function ($query) use ($search) {
//                         $query->where('title', 'like', "%{$search}%")
//                             ->orWhere(
//                                 'description',
//                                 'like',
//                                 "%{$search}%"
//                             );
//                     });
//                 }
//             )
//             ->when(
//                 $request->filled('status'),
//                 function ($query) use ($request) {
//                     $query->where('status', $request->status);
//                 }
//             )
//             ->when(
//                 $request->filled('priority'),
//                 function ($query) use ($request) {
//                     $query->where('priority', $request->priority);
//                 }
//             )
//             ->when(
//                 $request->filled('category_id'),
//                 function ($query) use ($request) {
//                     $query->where('category_id', $request->category_id);
//                 }
//             )
//             ->when(
//                 $request->filled('due_status'),
//                 function ($query) use ($request) {

//                     if ($request->due_status === 'overdue') {
//                         $query->whereNotNull('due_date')
//                             ->whereDate('due_date', '<', today())
//                             ->where('status', '!=', 'completed');
//                     }

//                     if ($request->due_status === 'due_soon') {
//                         $query->whereNotNull('due_date')
//                             ->whereBetween('due_date', [
//                                 today(),
//                                 today()->copy()->addDays(3),
//                             ])
//                             ->where('status', '!=', 'completed');
//                     }
//                 }
//             )
//             ->when(
//                 $user->hasRole('Admin')
//                     && $request->filled('assigned_to'),
//                 function ($query) use ($request) {
//                     $query->where(
//                         'assigned_to',
//                         $request->assigned_to
//                     );
//                 }
//             )
//             ->when(
//                 $request->boolean('overdue'),
//                 function ($query) {
//                     $query->whereNotNull('due_date')
//                         ->whereDate('due_date', '<', now()->toDateString())
//                         ->where('status', '!=', 'completed');
//                 }
//             )
//             ->latest()
//             ->paginate(
//                 $request->integer('per_page', 10)
//             );

//         return response()->json([
//             'success' => true,
//             'message' => 'Tasks retrieved successfully.',
//             'data' => $tasks,
//         ]);
//     }

//     public function store(
//         StoreTaskRequest $request,
//         TaskService $taskService,
//         TaskActivityService $activityService
//     ): JsonResponse {

//         $this->authorize('create', Task::class);

//         $user = $request->user();

//         $task = $taskService->createTask(
//             $request->validated(),
//             $user
//         );

//         $activityService->created(
//             $task,
//             $user
//         );

//         if ($task->assigned_to) {

//             $assignee = $task->assignee;

//             if ($assignee && $assignee->id !== $user->id) {

//                 $message = $user->name
//                     . ' assigned you a task: '
//                     . $task->title;

//                 $this->sendTaskNotification(
//                     $assignee,
//                     $task,
//                     $user,
//                     'task_assigned',
//                     $message
//                 );
//             }
//         }

//         $task->load([
//             'assignee:id,name,email',
//             'user:id,name,email',
//             'category:id,name,color',
//         ]);

//         return response()->json([
//             'success' => true,
//             'message' => 'Task created successfully.',
//             'data' => $task,
//         ], 201);
//     }

//     public function show(Task $task): JsonResponse
//     {
//         $this->authorize('view', $task);

//         $task->load([
//             'assignee:id,name,email',
//             'user:id,name,email',
//             'category:id,name,color',
//             'comments.user:id,name,email',
//             'attachments.user:id,name,email',
//         ]);

//         return response()->json([
//             'success' => true,
//             'message' => 'Task retrieved successfully.',
//             'data' => $task,
//         ]);
//     }

//     public function update(
//         UpdateTaskRequest $request,
//         Task $task,
//         TaskService $taskService,
//         TaskActivityService $activityService
//     ): JsonResponse {

//         $this->authorize('update', $task);

//         $user = $request->user();

//         $oldStatus = $task->status;
//         $oldPriority = $task->priority;
//         $oldAssignedTo = $task->assigned_to;
//         $oldCategoryId = $task->category_id;

//         $validated = $request->validated();

//         $task = $taskService->updateTask(
//             $task,
//             $validated
//         );

//         $generalChanges = collect([
//             'title',
//             'description',
//             'due_date',
//         ])->contains(
//             fn ($field) =>
//                 array_key_exists($field, $validated)
//                 && $task->wasChanged($field)
//         );

//         if ($generalChanges) {
//             $activityService->updated(
//                 $task,
//                 $user
//             );
//         }

//         if (
//             array_key_exists('status', $validated)
//             && $oldStatus !== $task->status
//         ) {
//             $activityService->statusChanged(
//                 $task,
//                 $user,
//                 $oldStatus,
//                 $task->status
//             );

//             $this->notifyTaskCreator(
//                 $task,
//                 $user,
//                 'status_updated',
//                 $user->name
//                     . ' updated the status of: '
//                     . $task->title
//             );
//         }

//         if (
//             array_key_exists('category_id', $validated)
//             && $oldCategoryId != $task->category_id
//         ) {
//             $oldCategoryName = $oldCategoryId
//                 ? TaskCategory::find($oldCategoryId)?->name
//                 : null;

//             $newCategoryName = $task->category?->name;

//             $activityService->categoryChanged(
//                 $task,
//                 $user,
//                 $oldCategoryName,
//                 $newCategoryName
//             );
//         }

//         if (
//             array_key_exists('priority', $validated)
//             && $oldPriority !== $task->priority
//         ) {
//             $activityService->priorityChanged(
//                 $task,
//                 $user,
//                 $oldPriority,
//                 $task->priority
//             );
//         }

//         if (
//             array_key_exists('assigned_to', $validated)
//             && $oldAssignedTo != $task->assigned_to
//         ) {
//             $employeeName = $task->assignee?->name;

//             $activityService->assigned(
//                 $task,
//                 $user,
//                 $employeeName
//             );

//             if ($task->assigned_to) {

//                 $assignee = $task->assignee;

//                 if ($assignee && $assignee->id !== $user->id) {

//                     $message = $user->name
//                         . ' assigned you a task: '
//                         . $task->title;

//                     $this->sendTaskNotification(
//                         $assignee,
//                         $task,
//                         $user,
//                         'task_assigned',
//                         $message
//                     );
//                 }
//             }
//         }

//         $task->load([
//             'assignee:id,name,email',
//             'user:id,name,email',
//             'category:id,name,color',
//         ]);

//         return response()->json([
//             'success' => true,
//             'message' => 'Task updated successfully.',
//             'data' => $task,
//         ]);
//     }

//     public function updateStatus(
//         UpdateTaskStatusRequest $request,
//         Task $task,
//         TaskService $taskService,
//         TaskActivityService $activityService
//     ): JsonResponse {

//         $this->authorize('updateStatus', $task);

//         $user = $request->user();

//         $oldStatus = $task->status;

//         $task = $taskService->updateStatus(
//             $task,
//             $request->validated()['status']
//         );

//         if ($oldStatus !== $task->status) {

//             $activityService->statusChanged(
//                 $task,
//                 $user,
//                 $oldStatus,
//                 $task->status
//             );

//             $this->notifyTaskCreator(
//                 $task,
//                 $user,
//                 'status_updated',
//                 $user->name
//                     . ' updated the status of: '
//                     . $task->title
//             );
//         }

//         $task->load([
//             'assignee:id,name,email',
//             'user:id,name,email',
//             'category:id,name,color',
//         ]);

//         return response()->json([
//             'success' => true,
//             'message' => 'Task status updated successfully.',
//             'data' => $task,
//         ]);
//     }

//     public function destroy(
//         Request $request,
//         Task $task
//     ): JsonResponse {

//         $this->authorize('delete', $task);

//         $task->delete();

//         return response()->json([
//             'success' => true,
//             'message' => 'Task deleted successfully.',
//         ]);
//     }

//     private function sendTaskNotification(
//         $recipient,
//         Task $task,
//         $actor,
//         string $action,
//         string $message
//     ): void {

//         $recipient->notify(
//             new TaskNotification(
//                 $task,
//                 $actor,
//                 $action,
//                 $message
//             )
//         );
//     }

//     private function notifyTaskCreator(
//         Task $task,
//         $actor,
//         string $action,
//         string $message
//     ): void {

//         $creator = $task->user;

//         if ($creator && $creator->id !== $actor->id) {

//             $this->sendTaskNotification(
//                 $creator,
//                 $task,
//                 $actor,
//                 $action,
//                 $message
//             );
//         }
//     }
//} 

