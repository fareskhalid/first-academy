<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->string('title_ar', 150);
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('type', 20);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('position')->default(1);
            $table->string('file_disk', 40)->nullable();
            $table->string('file_path')->nullable()->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('youtube_video_id', 20)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['course_offering_id', 'status', 'position'], 'course_materials_listing');
        });

        Schema::create('course_material_group', function (Blueprint $table) {
            $table->foreignId('course_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->primary(['course_material_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_material_group');
        Schema::dropIfExists('course_materials');
    }
};
