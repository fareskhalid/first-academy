<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('instructor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('changes')->nullable();
            $t->string('reason', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['instructor_id', 'created_at']);
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('notification_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('message_key');
            $t->json('parameters');
            $t->string('url');
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });
        Schema::create('system_heartbeats', function (Blueprint $t) {
            $t->string('name')->primary();
            $t->timestamp('seen_at');
        });
    }

    public function down(): void
    {
        foreach (['system_heartbeats', 'notification_events', 'notifications', 'audit_events'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
