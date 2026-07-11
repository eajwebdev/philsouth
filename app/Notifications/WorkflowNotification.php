<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A single database-channel notification for the inventory workflow. The payload
 * carries a title, message, url and icon so the front-end bell can render it
 * without a class-per-event explosion.
 */
class WorkflowNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $icon = 'bell',
        public array $extra = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon,
            ...$this->extra,
        ];
    }
}
