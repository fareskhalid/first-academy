<?php

use App\Jobs\DeliverNotice;
use App\Jobs\QueueHeartbeat;
use App\Models\NotificationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    DB::table('system_heartbeats')->updateOrInsert(['name' => 'scheduler'], ['seen_at' => now()]);
    QueueHeartbeat::dispatch();
    NotificationEvent::whereNull('delivered_at')->orderBy('created_at')->limit(100)->pluck('id')->each(fn ($id) => DeliverNotice::dispatch($id));
})->name('system-heartbeat-and-notice-retry')->everyMinute()->withoutOverlapping(2);
