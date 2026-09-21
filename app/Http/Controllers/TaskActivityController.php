<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaskActivityController extends Controller
{
    public function index(
        Request $request,
        Task $task
    ): JsonResponse {
        $this->authorize('view', $task);

        $activities = $task->activities()
            ->with('user:id,name,email')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Activity history retrieved successfully.',
            'data' => $activities,
        ]);
    }
}