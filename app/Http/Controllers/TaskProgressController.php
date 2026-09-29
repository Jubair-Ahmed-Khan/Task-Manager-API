<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskProgressController extends Controller
{
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'progress_percentage' => [
                'required',
                'integer',
                'min:0',
                'max:100',
            ],
        ]);

        $user = $request->user();

        $task = Task::visibleTo($user)->findOrFail($id);

        $task->progress_percentage =
            $validated['progress_percentage'];

        $task->save();

        return response()->json([
            'message' => 'Task progress updated successfully.',
            'data' => [
                'id' => $task->id,
                'progress_percentage' =>
                    $task->progress_percentage,
            ],
        ]);
    }
}