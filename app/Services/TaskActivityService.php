<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

class TaskActivityService
{
    public function created(Task $task, User $user): TaskActivity
    {
        return $this->record(
            task: $task,
            user: $user,
            action: 'created',
            description: 'Task created'
        );
    }

    public function updated(Task $task, User $user): TaskActivity
    {
        return $this->record(
            task: $task,
            user: $user,
            action: 'updated',
            description: 'Task updated'
        );
    }

    public function assigned(
        Task $task,
        User $user,
        ?string $employeeName
    ): TaskActivity {
        return $this->record(
            task: $task,
            user: $user,
            action: 'assigned',
            description: $employeeName
                ? "Task assigned to {$employeeName}"
                : 'Task assignment removed',
            newValue: $employeeName
        );
    }

    public function statusChanged(
        Task $task,
        User $user,
        ?string $oldStatus,
        ?string $newStatus
    ): TaskActivity {
        return $this->record(
            task: $task,
            user: $user,
            action: 'status_changed',
            description: "Status changed from {$oldStatus} to {$newStatus}",
            oldValue: $oldStatus,
            newValue: $newStatus
        );
    }

    public function priorityChanged(
        Task $task,
        User $user,
        ?string $oldPriority,
        ?string $newPriority
    ): TaskActivity {
        return $this->record(
            task: $task,
            user: $user,
            action: 'priority_changed',
            description: "Priority changed from {$oldPriority} to {$newPriority}",
            oldValue: $oldPriority,
            newValue: $newPriority
        );
    }

    private function record(
        Task $task,
        User $user,
        string $action,
        string $description,
        ?string $oldValue = null,
        ?string $newValue = null
    ): TaskActivity {
        return $task->activities()->create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }
}