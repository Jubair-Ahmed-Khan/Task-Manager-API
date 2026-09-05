<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'assigned_to', 
    ];

    protected $attributes = [
        'status' => 'pending',
        'priority' => 'medium',
    ];
    
    protected $casts = [
        'due_date' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    { 
        return $this->belongsTo( User::class, 'assigned_to' ); 
    }

    public function getIsOverdueAttribute(): bool 
    { 
        if (!$this->due_date) 
        { 
            return false; 
        } 
        return $this->due_date->isPast() && $this->status !== 'completed'; 
    }

    public function scopeVisibleTo( Builder $query, User $user ): Builder 
    { 
        if ($user->hasRole('Admin')) 
        { 
            return $query; 
        } 
        return $query->where( 'assigned_to', $user->id ); 
    }

    public function scopeForUser(Builder $query, int $userId): Builder 
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query) use ($search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            });
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, function (Builder $query) use ($status) {
            $query->where('status', $status);
        });
    }

    public function scopePriority(Builder $query, ?string $priority): Builder
    {
        return $query->when($priority, function (Builder $query) use ($priority) {
            $query->where('priority', $priority);
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->priority($filters['priority'] ?? null);
    }

    public function scopeSort(Builder $query, string $sortBy = 'created_at', string $sortDirection = 'desc'): Builder 
    {
        $allowedSorts = [
            'created_at',
            'updated_at',
            'due_date',
            'priority',
            'status',
        ];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        return $query->orderBy($sortBy, $sortDirection);
    }
}