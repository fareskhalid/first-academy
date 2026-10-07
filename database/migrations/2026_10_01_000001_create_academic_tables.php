<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $t->string('name');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $t->string('name');
            $t->string('code', 40);
            $t->text('description')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->unique(['instructor_id', 'code']);
        });
        Schema::create('course_offerings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->foreignId('semester_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('timezone')->default('Africa/Cairo');
            $t->unsignedBigInteger('fee_minor')->default(0);
            $t->string('currency', 3)->default('EGP');
            $t->string('status', 20)->default('draft');
            $t->boolean('uses_groups')->default(false);
            $t->boolean('self_enrollment')->default(true);
            $t->timestamps();
        });
        Schema::create('groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $t->string('name', 80);
            $t->unsignedInteger('capacity')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->unique(['course_offering_id', 'name']);
        });
        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->unsignedInteger('position');
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->unique(['course_offering_id', 'position']);
        });
        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $t->foreignId('group_id')->constrained()->restrictOnDelete();
            $t->string('status', 20)->default('enrolled');
            $t->unsignedBigInteger('agreed_fee_minor');
            $t->string('currency', 3);
            $t->timestamp('joined_at');
            $t->timestamp('withdrawn_at')->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'course_offering_id']);
        });
        Schema::create('group_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $t->foreignId('group_id')->constrained()->restrictOnDelete();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->string('reason', 500);
            $t->timestamps();
            $t->index(['enrollment_id', 'ends_at']);
        });
        Schema::create('enrollment_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $t->foreignId('group_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('transfer_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $t->foreignId('group_id')->constrained()->restrictOnDelete();
            $t->string('reason', 500);
            $t->string('status', 20)->default('pending');
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['transfer_requests', 'enrollment_invitations', 'group_memberships', 'enrollments', 'lessons', 'groups', 'course_offerings', 'courses', 'semesters'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
