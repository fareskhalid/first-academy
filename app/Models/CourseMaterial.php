<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAcademicFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseMaterial extends Model
{
    use HasFactory, HasLocalizedAcademicFields;

    public const TYPES = ['pdf', 'image', 'youtube'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'position' => 'integer',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function offering()
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'course_material_group');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNull('archived_at');
    }

    public function getYoutubeEmbedUrlAttribute(): ?string
    {
        return $this->youtube_video_id
            ? 'https://www.youtube-nocookie.com/embed/'.$this->youtube_video_id
            : null;
    }
}
