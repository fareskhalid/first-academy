<?php

namespace App\Jobs;

use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DeliverNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $eventId) {}

    public function backoff(): array
    {
        return [5, 30, 60];
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $event = NotificationEvent::lockForUpdate()->find($this->eventId);
            if (! $event || $event->delivered_at) {
                return;
            }
            DB::table('notifications')->insertOrIgnore([
                'id' => $event->id, 'type' => 'course-system', 'notifiable_type' => User::class, 'notifiable_id' => $event->user_id,
                'data' => json_encode(['key' => $event->message_key, 'parameters' => $event->parameters, 'url' => $event->url], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $event->update(['delivered_at' => now()]);
        });
    }
}
