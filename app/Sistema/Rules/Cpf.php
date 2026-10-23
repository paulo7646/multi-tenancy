<?php

namespace App\Forms\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $cpf = preg_replace('/\D/', '', (string) $value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('CPF inválido.');

            return;
        }

        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;

            for ($i = 0; $i < $digit; $i++) {
                $sum += (int) $cpf[$i] * (($digit + 1) - $i);
            }

            if ((int) $cpf[$digit] !== ((10 * $sum) % 11) % 10) {
                $fail('CPF inválido.');

                return;
            }
        }
    }
}
