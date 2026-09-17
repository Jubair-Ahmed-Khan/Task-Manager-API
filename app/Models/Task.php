<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Task extends Model
{
    use HasFactory;


    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'assigned_to',
    ];


    /*
    |--------------------------------------------------------------------------
    | Default Attributes
    |--------------------------------------------------------------------------
    */

    protected $attributes = [
        'status' => 'pending',
        'priority' => 'medium',
    ];


    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'due_date' => 'date:Y-m-d',
    ];


    /*
    |--------------------------------------------------------------------------
    | Appended Attributes
    |--------------------------------------------------------------------------
    |
    | These attributes will automatically be included
    | in the API JSON response.
    |
    */

    protected $appends = [
        'is_overdue',
        'is_due_soon',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)
            ->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Overdue Attribute
    |--------------------------------------------------------------------------
    |
    | Task is overdue when:
    |
    | - Due date exists
    | - Due date is before today
    | - Task is not completed
    |
    */

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        return $this->due_date->lt(today())
            && $this->status !== 'completed';
    }


    /*
    |--------------------------------------------------------------------------
    | Due Soon Attribute
    |--------------------------------------------------------------------------
    |
    | Task is due soon when:
    |
    | - Due date exists
    | - Due date is today or within the next 3 days
    | - Task is not completed
    |
    */

    public function getIsDueSoonAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        return $this->due_date->between(
            today(),
            today()->copy()->addDays(3)
        )
        && $this->status !== 'completed';
    }


    /*
    |--------------------------------------------------------------------------
    | Visible To Scope
    |--------------------------------------------------------------------------
    */

    public function scopeVisibleTo(
        Builder $query,
        User $user
    ): Builder {

        if ($user->hasRole('Admin')) {

            return $query;

        }

        return $query->where(
            'assigned_to',
            $user->id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | For User Scope
    |--------------------------------------------------------------------------
    */

    public function scopeForUser(
        Builder $query,
        int $userId
    ): Builder {

        return $query->where(
            'user_id',
            $userId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Search Scope
    |--------------------------------------------------------------------------
    */

    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {

        return $query->when(
            $search,
            function (
                Builder $query
            ) use ($search) {

                $query->where(
                    function (
                        Builder $query
                    ) use ($search) {

                        $query
                            ->where(
                                'title',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            );

                    }
                );

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Status Scope
    |--------------------------------------------------------------------------
    */

    public function scopeStatus(
        Builder $query,
        ?string $status
    ): Builder {

        return $query->when(
            $status,
            function (
                Builder $query
            ) use ($status) {

                $query->where(
                    'status',
                    $status
                );

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Priority Scope
    |--------------------------------------------------------------------------
    */

    public function scopePriority(
        Builder $query,
        ?string $priority
    ): Builder {

        return $query->when(
            $priority,
            function (
                Builder $query
            ) use ($priority) {

                $query->where(
                    'priority',
                    $priority
                );

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Overdue Scope
    |--------------------------------------------------------------------------
    */

    public function scopeOverdue(
        Builder $query
    ): Builder {

        return $query
            ->whereNotNull('due_date')
            ->whereDate(
                'due_date',
                '<',
                today()
            )
            ->where(
                'status',
                '!=',
                'completed'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Due Soon Scope
    |--------------------------------------------------------------------------
    */

    public function scopeDueSoon(
        Builder $query,
        int $days = 3
    ): Builder {

        return $query
            ->whereNotNull('due_date')
            ->whereBetween(
                'due_date',
                [
                    today(),
                    today()->copy()->addDays($days),
                ]
            )
            ->where(
                'status',
                '!=',
                'completed'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Filter Scope
    |--------------------------------------------------------------------------
    */

    public function scopeFilter(
        Builder $query,
        array $filters
    ): Builder {

        $query
            ->search(
                $filters['search'] ?? null
            )
            ->status(
                $filters['status'] ?? null
            )
            ->priority(
                $filters['priority'] ?? null
            );


        /*
        |--------------------------------------------------------------------------
        | Due Status Filter
        |--------------------------------------------------------------------------
        */

        if (
            !empty($filters['due_status'])
        ) {

            if (
                $filters['due_status'] === 'overdue'
            ) {

                $query->overdue();

            }

            if (
                $filters['due_status'] === 'due_soon'
            ) {

                $query->dueSoon();

            }

        }


        return $query;
    }


    /*
    |--------------------------------------------------------------------------
    | Sort Scope
    |--------------------------------------------------------------------------
    */

    public function scopeSort(
        Builder $query,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): Builder {

        $allowedSorts = [
            'created_at',
            'updated_at',
            'due_date',
            'priority',
            'status',
        ];


        if (
            !in_array(
                $sortBy,
                $allowedSorts
            )
        ) {

            $sortBy = 'created_at';

        }


        if (
            !in_array(
                $sortDirection,
                [
                    'asc',
                    'desc',
                ]
            )
        ) {

            $sortDirection = 'desc';

        }


        return $query->orderBy(
            $sortBy,
            $sortDirection
        );
    }
}