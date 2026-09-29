<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'priority' => [
                'required',
                'in:low,medium,high',
            ],
            'status' => [
                'nullable',
                'in:pending,in_progress,completed',
            ],
            'assigned_to' => [
                'nullable',
                'exists:users,id',
            ],
            'due_date' => [
                'nullable',
                'date',
            ],
            'category_id' => [
                'nullable',
                'exists:task_categories,id',
            ],
            'progress_percentage' => [
                'sometimes',
                'integer',
                'min:0',
                'max:100',
            ],
            'estimated_minutes' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
