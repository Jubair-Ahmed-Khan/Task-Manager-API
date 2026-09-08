<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Task;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = User::whereHas('roles', function ($query) {
            $query->where('name', 'Employee');
        })
        ->select([
            'id',
            'name',
            'email',
            'created_at',
        ])
        ->orderBy('name')
        ->get();

        return response()->json([
            'message' => 'Employees retrieved successfully',
            'data' => $employees,
        ]);
    }
    public function performance()
    {
        $employees = User::role('Employee')
            ->withCount([
                'assignedTasks as total_tasks',

                'assignedTasks as completed_tasks' => function ($query) {
                    $query->where(
                        'status',
                        'completed'
                    );
                },

                'assignedTasks as pending_tasks' => function ($query) {
                    $query->where(
                        'status',
                        'pending'
                    );
                },

                'assignedTasks as in_progress_tasks' => function ($query) {
                    $query->where(
                        'status',
                        'in_progress'
                    );
                },

                'assignedTasks as overdue_tasks' => function ($query) {

                    $query
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
                },
            ])
            ->get()
            ->map(function ($employee) {

                $employee->completion_rate =
                    $employee->total_tasks > 0
                        ? round(
                            (
                                $employee->completed_tasks /
                                $employee->total_tasks
                            ) * 100,
                            2
                        )
                        : 0;

                return $employee;
            });

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }
}