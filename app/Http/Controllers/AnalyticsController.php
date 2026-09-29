<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskTimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request)
    {
        abort_unless(
            $request->user()->hasRole('Admin'),
            403,
            'Only Admin can access analytics.'
        );

        $total = Task::count();

        $completed = Task::where('status', 'Completed')
            ->count();

        $inProgress = Task::where('status', 'In Progress')
            ->count();

        $overdue = Task::whereDate('due_date', '<', today())
            ->where('status', '!=', 'Completed')
            ->count();

        $byStatus = Task::select(
            'status',
            DB::raw('COUNT(*) as total')
        )
            ->groupBy('status')
            ->get();

        $employeeWorkload = User::role('Employee')
            ->withCount([
                'assignedTasks as total_tasks',
                'assignedTasks as completed_tasks' =>
                    function ($query) {
                        $query->where(
                            'status',
                            'Completed'
                        );
                    },
            ])
            ->get([
                'id',
                'name',
            ]);

        $timeByTask = Task::leftJoin(
            'task_time_entries',
            'tasks.id',
            '=',
            'task_time_entries.task_id'
        )
            ->select(
                'tasks.id',
                'tasks.title',
                'tasks.estimated_minutes',
                DB::raw(
                    'COALESCE(SUM(task_time_entries.duration_seconds), 0) / 60 AS actual_minutes'
                )
            )
            ->groupBy(
                'tasks.id',
                'tasks.title',
                'tasks.estimated_minutes'
            )
            ->orderByDesc('actual_minutes')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'summary' => [
                    'total_tasks' => $total,
                    'completed_tasks' => $completed,
                    'in_progress_tasks' => $inProgress,
                    'overdue_tasks' => $overdue,
                    'completion_rate' => $total > 0
                        ? round(($completed / $total) * 100, 2)
                        : 0,
                ],
                'tasks_by_status' => $byStatus,
                'employee_workload' => $employeeWorkload,
                'time_by_task' => $timeByTask,
            ],
        ]);
    }
}