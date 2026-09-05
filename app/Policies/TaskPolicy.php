<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'Admin',
            'Employee',
        ]);
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->hasRole('Admin')) 
        {
            return true;
        }
        return $task->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Task $task): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->hasRole('Admin');
    }

    public function updateStatus(User $user, Task $task): bool 
    {
        return $user->hasRole('Employee') && $task->assigned_to === $user->id;
    }
}
