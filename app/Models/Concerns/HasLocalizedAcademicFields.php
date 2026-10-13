<?php

namespace App\Models\Concerns;

trait HasLocalizedAcademicFields
{
    public function localized(string $attribute, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $fallback = $this->getAttribute($attribute);
        $translated = $locale === 'ar' ? $this->getAttribute($attribute.'_ar') : null;

        return filled($translated) ? $translated : $fallback;
    }

    public function getDisplayNameAttribute(): ?string
    {
        return $this->localized('name');
    }

    public function getDisplayTitleAttribute(): ?string
    {
        return $this->localized('title');
    }

    public function getDisplayDescriptionAttribute(): ?string
    {
        return $this->localized('description');
    }
}
