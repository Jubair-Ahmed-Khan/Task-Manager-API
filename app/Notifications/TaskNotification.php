<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskNotification extends Notification
{
    use Queueable;

    protected $task;
    protected $actor;
    protected $action;
    protected $message;

    public function __construct(
        $task,
        $actor,
        string $action,
        string $message
    ) {
        $this->task = $task;
        $this->actor = $actor;
        $this->action = $action;
        $this->message = $message;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'action' => $this->action,
            'message' => $this->message,
            'url' => '/tasks/' . $this->task->id,
        ];
    }
}