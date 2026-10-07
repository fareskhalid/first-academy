<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 16)->unique();
            $table->string('student_code', 24)->nullable()->unique();
            $table->string('role', 20)->default('student')->index();
            $table->string('status', 20)->default('active');
            $table->string('locale', 2)->default('en');
            $table->string('password');
            $table->unsignedInteger('session_version')->default(1);
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('temporary_password_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('whatsapp_phone', 16);
            $table->timestamp('whatsapp_declared_at');
            $table->string('university')->nullable();
            $table->string('university_student_id')->nullable();
            $table->timestamps();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('users');
    }
};
