<?php

namespace App\Http\Controllers\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Admin Dashboard
        if ($user->hasRole('Admin')) {

            $totalTasks = Task::count();
            $pendingTasks = Task::where('status', 'pending')->count();
            $inProgressTasks = Task::where('status', 'in_progress')->count();
            $completedTasks = Task::where('status', 'completed')->count();
            $overdueTasks = Task::whereDate('due_date', '<',
                now()->toDateString()
            )
            ->whereNotIn('status', [
                'completed',
                'cancelled',
            ])
            ->count();


            $totalEmployees = User::role('Employee')->count();


            return response()->json([
                'data' => [
                    'total_tasks' => $totalTasks,
                    'pending_tasks' => $pendingTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'completed_tasks' => $completedTasks,
                    'overdue_tasks' => $overdueTasks,
                    'total_employees' => $totalEmployees,
                    'my_tasks' => 0,
                ],
            ]);
        }


        // Employee Dashboard
        $myTasks = Task::where('assigned_to', $user->id);
        $totalTasks = (clone $myTasks)->count();
        $pendingTasks = (clone $myTasks)
            ->where('status', 'pending')
            ->count();
        $inProgressTasks = (clone $myTasks)
            ->where('status', 'in_progress')
            ->count();
        $completedTasks = (clone $myTasks)
            ->where('status', 'completed')
            ->count();
        $overdueTasks = (clone $myTasks)
            ->whereDate(
                'due_date',
                '<',
                now()->toDateString()
            )
            ->whereNotIn('status', [
                'completed',
                'cancelled',
            ])
            ->count();


        return response()->json([
            'data' => [
                'total_tasks' => $totalTasks,
                'pending_tasks' => $pendingTasks,
                'in_progress_tasks' => $inProgressTasks,
                'completed_tasks' => $completedTasks,
                'overdue_tasks' => $overdueTasks,
                'total_employees' => 0,
                'my_tasks' => $totalTasks,
            ],
        ]);
    }
}
