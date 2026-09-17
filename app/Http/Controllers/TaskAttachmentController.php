<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    /**
     * Get attachments for a task.
     */
    public function index(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $attachments = $task->attachments()
            ->with('user:id,name,email')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Attachments retrieved successfully.',
            'data' => $attachments,
        ]);
    }

    /**
     * Upload attachment.
     */
    public function store(
        StoreTaskAttachmentRequest $request,
        Task $task
    ): JsonResponse {
        $this->authorize('attach', $task);

        $file = $request->file('file');

        $fileName = uniqid() . '_' . $file->getClientOriginalName();

        $filePath = $file->storeAs(
            'task-attachments',
            $fileName,
            'public'
        );

        $attachment = $task->attachments()->create([
            'user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        $attachment->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Attachment uploaded successfully.',
            'data' => $attachment,
        ], 201);
    }

    /**
     * Download attachment.
     */
    public function download(
        Request $request,
        TaskAttachment $attachment
    )
    {
        $this->authorize('view', $attachment);

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment file not found.',
            ], 404);
        }

        return Storage::disk('public')->download(
            $attachment->file_path,
            $attachment->original_name
        );
    }

    /**
     * Delete attachment.
     */
    public function destroy(
        Request $request,
        TaskAttachment $attachment
    ): JsonResponse {
        $this->authorize('delete', $attachment);

        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully.',
        ]);
    }
}