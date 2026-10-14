<?php

namespace App\Rules;

use App\Support\OperatingHours;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidOperatingHours implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (OperatingHours::errors($value) as $message) {
            $fail($message);
        }
    }
}
