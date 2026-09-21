<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

class TaskService
{
    // Get tasks for the authenticated user with optional filters
    public function getTasks(User $user, array $filters = []) 
    {
        $query = Task::query()
            ->with([
                'assignee',
                'user',
            ]);

        if ($user->hasRole('Employee')) 
        {
            $query->where('assigned_to', $user->id);
        }

        if (!empty($filters['search'])) 
        {

            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where(
                    'title',
                    'like',
                    "%{$search}%"
                );
                $q->orWhere(
                    'description',
                    'like',
                    "%{$search}%"
                );
            });
        }

        if (!empty($filters['status'])) 
        {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) 
        {
            $query->where('priority', $filters['priority']);
        }

        if ($user->hasRole('Admin') && !empty($filters['assigned_to'])) 
        {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query
            ->latest()
            ->paginate(
                $filters['per_page'] ?? 10
            );
    }

    // Get a single task with its relationships
    public function getTask(Task $task): Task
    {
        return $task->load([
            'assignee',
            'user',
        ]);
    }

    // Create a new task and associate it with the creator
    public function createTask(array $data, User $creator): Task 
    {
        $data['user_id'] = $creator->id;
        $task = Task::create($data);

        return $task->load([
            'assignee',
            'user',
        ]);
    }

    // Update an existing task with new data
    // public function updateTask(Task $task, array $data): Task 
    // {
    //     $task->update($data);

    //     return $task->fresh([
    //         'assignee',
    //         'user',
    //     ]);
    // }
    public function updateTask(Task $task, array $data): Task
    {
        $task->update($data);

        return $task->load([
            'assignee:id,name,email',
            'user:id,name,email',
        ]);
    }

    // Update the status of a task
    public function updateStatus(Task $task, string $status): Task 
    {
        $task->update([
            'status' => $status,
        ]);

        return $task->fresh([
            'assignee',
            'user',
        ]);
    }
    

    // Delete a task and return the result
    public function deleteTask(Task $task): bool
    {
        return $task->delete();
    }
}