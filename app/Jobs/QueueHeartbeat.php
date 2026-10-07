<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        DB::table('system_heartbeats')->updateOrInsert(['name' => 'queue'], ['seen_at' => now()]);
    }
}
