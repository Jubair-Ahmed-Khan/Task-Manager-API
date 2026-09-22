<?php

namespace App\Policies;

use App\Models\TaskCategory;
use App\Models\User;

class TaskCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, TaskCategory $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, TaskCategory $category): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, TaskCategory $category): bool
    {
        return $user->hasRole('Admin');
    }
}