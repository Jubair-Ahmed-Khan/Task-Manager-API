<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'is_overdue' => $this->due_date ? $this->due_date->isPast() && $this->status !== 'completed' : false,
            'assigned_to' => $this->assigned_to, 
            'assignee' => $this->when( 
                $this->relationLoaded('assignee'), 
                function () { 
                    return $this->assignee 
                    ? [ 'id' => $this->assignee->id, 
                        'name' => $this->assignee->name, 
                        'email' => $this->assignee->email, 
                      ] 
                    : null; 
                } 
            ),
            'created_by' => $this->when( 
                $this->relationLoaded('user'), 
                function () { 
                    if (!$this->user) { 
                        return null; 
                    } 
                    return [ 
                        'id' => $this->user->id, 
                        'name' => $this->user->name, 
                        'email' => $this->user->email, 
                    ]; 
                } 
            ),
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}