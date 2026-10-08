<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->string('timezone', 80);
            $table->timestamp('scheduled_start_at');
            $table->timestamp('scheduled_end_at');
            $table->timestamp('attendance_opens_at');
            $table->timestamp('attendance_closes_at');
            $table->timestamp('late_after_at');
            $table->string('location')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('qr_revision')->default(1);
            $table->text('conflict_override_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('roster_captured_at')->nullable();
            $table->timestamp('absences_finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'lesson_id', 'scheduled_start_at'], 'sessions_group_lesson_start_unique');
            $table->index(['course_offering_id', 'scheduled_start_at'], 'sessions_offering_start_index');
            $table->index(['status', 'attendance_opens_at', 'attendance_closes_at'], 'sessions_window_index');
        });

        Schema::create('session_schedule_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('session_roster_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('expected');
            $table->string('denial_reason', 80)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique(['class_session_id', 'enrollment_id'], 'session_roster_enrollment_unique');
        });

        Schema::create('qr_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('token_hash', 64)->unique();
            $table->string('short_code_hash', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['class_session_id', 'expires_at']);
        });

        Schema::create('attendance_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['class_session_id', 'enrollment_id'], 'attendance_authorization_unique');
        });

        Schema::create('attendance_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('qr_credential_id')->constrained()->restrictOnDelete();
            $table->string('browser_session_hash', 64);
            $table->timestamp('accepted_at');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['class_session_id', 'enrollment_id']);
        });

        Schema::create('course_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['enrollment_id', 'valid_from', 'valid_until'], 'entitlement_validity_index');
        });

        Schema::create('access_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('scope', 40)->default('attendance');
            $table->string('reason', 500);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['enrollment_id', 'scope', 'valid_from'], 'waiver_access_index');
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('session_roster_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->string('method', 30);
            $table->string('access_source', 40)->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['enrollment_id', 'lesson_id'], 'attendance_enrollment_lesson_unique');
            $table->index(['class_session_id', 'status']);
        });

        Schema::create('attendance_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('attendance_denials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reason', 80);
            $table->timestamp('attempted_at');
            $table->timestamps();
            $table->index(['class_session_id', 'reason']);
        });

        Schema::table('notification_events', function (Blueprint $table) {
            $table->string('dedupe_key', 180)->nullable()->unique()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('notification_events', fn (Blueprint $table) => $table->dropUnique(['dedupe_key']));
        Schema::table('notification_events', fn (Blueprint $table) => $table->dropColumn('dedupe_key'));

        foreach ([
            'attendance_denials', 'attendance_revisions', 'attendances', 'access_waivers',
            'course_entitlements', 'attendance_intents', 'attendance_authorizations',
            'qr_credentials', 'session_roster_entries', 'session_schedule_revisions', 'class_sessions',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
