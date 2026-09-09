<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaskCommentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Get Comments
    |--------------------------------------------------------------------------
    */

    public function index(Task $task): JsonResponse
    {
        $user = request()->user();

        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        if (
            !$user->hasRole('Admin')
            &&
            $task->assigned_to !== $user->id
        ) {
            abort(403, 'Unauthorized.');
        }


        $comments = $task
            ->comments()
            ->with('user:id,name,email')
            ->get();


        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Store Comment
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        Task $task
    ): JsonResponse {

        $user = $request->user();


        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        if (
            !$user->hasRole('Admin')
            &&
            $task->assigned_to !== $user->id
        ) {
            abort(403, 'Unauthorized.');
        }


        $validated = $request->validate([
            'comment' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);


        $comment = TaskComment::create([
            'task_id' => $task->id,

            'user_id' => $user->id,

            'comment' => $validated['comment'],
        ]);


        $comment->load(
            'user:id,name,email'
        );


        return response()->json([
            'success' => true,

            'message' => 'Comment added successfully.',

            'data' => $comment,
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Comment
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        TaskComment $comment
    ): JsonResponse {

        $user = $request->user();


        /*
        |--------------------------------------------------------------------------
        | Only Admin or Comment Owner
        |--------------------------------------------------------------------------
        */

        if (
            !$user->hasRole('Admin')
            &&
            $comment->user_id !== $user->id
        ) {
            abort(403, 'Unauthorized.');
        }


        $comment->delete();


        return response()->json([
            'success' => true,

            'message' => 'Comment deleted successfully.',
        ]);
    }
}