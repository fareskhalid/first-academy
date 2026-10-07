<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class SystemCheck extends Command
{
    protected $signature = 'system:check {--wait=0 : Bounded seconds to wait for the scheduler and queue}';

    protected $description = 'Check database, Redis, private storage, queue delivery, and scheduler heartbeat';

    public function handle(): int
    {
        try {
            DB::select('SELECT 1');
            Redis::connection()->ping();
            $path = 'health/'.bin2hex(random_bytes(8));
            if (! Storage::disk('local')->put($path, 'ready') || Storage::disk('local')->get($path) !== 'ready') {
                throw new \RuntimeException('Private storage is not writable.');
            }
            Storage::disk('local')->delete($path);
            $deadline = time() + min(90, max(0, (int) $this->option('wait')));
            do {
                $healthy = collect(['scheduler', 'queue'])->every(function ($name) {
                    $at = DB::table('system_heartbeats')->where('name', $name)->value('seen_at');

                    return $at && Carbon::parse($at)->gt(now()->subMinutes(3));
                });
                if ($healthy) {
                    break;
                }
                if (time() >= $deadline) {
                    throw new \RuntimeException('Scheduler or queue heartbeat missing/stale. Check service logs.');
                }
                usleep(500000);
            } while (true);
            $this->info('PASS: database, Redis, private storage, queue, scheduler.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
