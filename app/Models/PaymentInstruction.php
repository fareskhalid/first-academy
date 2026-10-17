<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentInstruction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function offering()
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function getDisplayInstructionsAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->instructions_ar : $this->instructions;
    }

    public function getDisplayReferenceInstructionsAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->reference_instructions_ar : $this->reference_instructions;
    }
}
