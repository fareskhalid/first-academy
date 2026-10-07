<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    public function __construct(private bool $egyptian = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valid = is_string($value) && ($this->egyptian ? Phone::egyptian($value) : Phone::international($value));
        if (! $valid) {
            $fail($this->egyptian ? __('ui.invalid_phone') : __('ui.invalid_whatsapp'));
        }
    }
}
