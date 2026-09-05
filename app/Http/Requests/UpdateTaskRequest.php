<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'priority' => [
                'sometimes',
                'required',
                'in:low,medium,high',
            ],
            'status' => [
                'sometimes',
                'required',
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
        ];
    }
}