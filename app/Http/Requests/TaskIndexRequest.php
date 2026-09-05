<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class TaskIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'in_progress',
                    'completed',
                ]),
            ],
            'priority' => [
                'nullable',
                Rule::in([
                    'low',
                    'medium',
                    'high',
                ]),
            ],
            'sort_by' => [
                'nullable',
                Rule::in([
                    'created_at',
                    'updated_at',
                    'due_date',
                    'priority',
                    'status',
                ]),
            ],
            'sort_direction' => [
                'nullable',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}