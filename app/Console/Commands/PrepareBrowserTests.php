<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PrepareBrowserTests extends Command
{
    protected $signature = 'test:prepare-browser';

    protected $description = 'Initialize only the isolated browser-test schema and synthetic fixtures';

    public function handle(): int
    {
        if (! app()->environment('testing') || config('database.connections.mysql.database') !== 'course_system_browser') {
            $this->error('Refusing to initialize a non-browser database.');

            return self::FAILURE;
        }
        // Non-destructive: repeated runs keep data; browser tests generate unique accounts.
        Artisan::call('migrate', ['--force' => true]);
        putenv('DEMO_PASSWORD=BrowserTest123!');
        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
        $teacher = User::where('phone', '+201000000001')->firstOrFail();
        $teacher->update([
            'password' => 'BrowserTest123!',
            'status' => 'active',
            'session_version' => 1,
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
        ]);
        $this->info('Browser fixtures ready.');

        return self::SUCCESS;
    }
}
