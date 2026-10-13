<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->string('name_ar', 150)->nullable()->after('name');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->string('name_ar', 150)->nullable()->after('name');
            $table->text('description_ar')->nullable()->after('description');
        });
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('title_ar', 150)->nullable()->after('title');
            $table->dropColumn('timezone');
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->string('name_ar', 80)->nullable()->after('name');
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('title_ar', 150)->nullable()->after('title');
        });
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->string('timezone', 80)->default('Africa/Cairo')->after('lesson_id');
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('title_ar');
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('name_ar');
        });
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('timezone', 80)->default('Africa/Cairo')->after('title');
            $table->dropColumn('title_ar');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'description_ar']);
        });
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn('name_ar');
        });
    }
};
