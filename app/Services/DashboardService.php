<?php

namespace App\Services;

use App\Models\Task;

class DashboardService
{
    /**
     * Get task statistics for the authenticated user.
     */
    public function getStats(): array
    {
        $userId = auth()->id();
        $query = Task::query()->forUser($userId);

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)
                ->where('status', 'pending')
                ->count(),
            'in_progress' => (clone $query)
                ->where('status', 'in_progress')
                ->count(),
            'completed' => (clone $query)
                ->where('status', 'completed')
                ->count(),
        ];
    }
}
