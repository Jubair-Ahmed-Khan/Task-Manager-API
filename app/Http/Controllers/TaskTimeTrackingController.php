<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskTimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskTimeTrackingController extends Controller
{
    private function findTask(Request $request, int $id): Task
    {
        return Task::visibleTo($request->user())
            ->findOrFail($id);
    }

    public function start(Request $request, int $id)
    {
        $task = $this->findTask($request, $id);
        $user = $request->user();

        return DB::transaction(function () use ($task, $user) {
            $activeTimer = TaskTimeEntry::where(
                'user_id',
                $user->id
            )
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($activeTimer) {
                throw ValidationException::withMessages([
                    'timer' => [
                        'You already have an active timer. Stop it first.'
                    ],
                ]);
            }

            $entry = TaskTimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => now(),
                'duration_seconds' => 0,
            ]);

            return response()->json([
                'message' => 'Timer started.',
                'data' => $entry,
            ], 201);
        });
    }

    public function stop(Request $request, int $id)
    {
        $task = $this->findTask($request, $id);
        $user = $request->user();

        $entry = TaskTimeEntry::where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->firstOrFail();

        $entry->ended_at = now();

        $entry->duration_seconds = max(
            0,
            $entry->started_at->diffInSeconds($entry->ended_at)
        );

        $entry->save();

        return response()->json([
            'message' => 'Timer stopped.',
            'data' => $entry,
        ]);
    }

    public function entries(Request $request, int $id)
    {
        $task = $this->findTask($request, $id);

        $query = $task->timeEntries()
            ->with('user:id,name');

        // Employees see their own time entries.
        if (!$request->user()->hasRole('Admin')) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json([
            'data' => $query
                ->latest()
                ->paginate(10),
        ]);
    }

    public function summary(Request $request, int $id)
    {
        $task = $this->findTask($request, $id);

        $query = $task->timeEntries();

        if (!$request->user()->hasRole('Admin')) {
            $query->where('user_id', $request->user()->id);
        }

        $totalSeconds = (clone $query)
            ->sum('duration_seconds');

        $activeEntry = (clone $query)
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if ($activeEntry) {
            $totalSeconds += $activeEntry->started_at
                ->diffInSeconds(now());
        }

        return response()->json([
            'data' => [
                'total_seconds' => $totalSeconds,
                'total_minutes' => round(
                    $totalSeconds / 60,
                    2
                ),
                'estimated_minutes' =>
                    $task->estimated_minutes,
                'active_timer' => $activeEntry,
            ],
        ]);
    }
}