<?php

namespace App\Policies;

use App\Models\TaskAttachment;
use App\Models\User;

class TaskAttachmentPolicy
{
    public function view(User $user, TaskAttachment $attachment): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return $user->hasRole('Employee')
            && $attachment->task->assigned_to === $user->id;
    }

    public function delete(User $user, TaskAttachment $attachment): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return $user->hasRole('Employee')
            && $attachment->user_id === $user->id
            && $attachment->task->assigned_to === $user->id;
    }
}