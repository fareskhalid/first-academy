<?php

namespace App\Support;

use App\Jobs\DeliverNotice;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class Notices
{
    public static function send(User $user, string $key, array $parameters = [], string $url = '/dashboard'): void
    {
        $event = NotificationEvent::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'message_key' => $key, 'parameters' => $parameters, 'url' => $url]);
        DB::afterCommit(function () use ($event) {
            try {
                DeliverNotice::dispatch($event->id);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
