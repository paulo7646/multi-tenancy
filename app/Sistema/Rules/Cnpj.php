<?php

namespace App\Sistema\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $cnpj = preg_replace('/\D/', '', (string) $value);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('CNPJ inválido.');

            return;
        }

        foreach ([12 => [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], 13 => [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $position => $weights) {
            $sum = 0;

            foreach ($weights as $i => $weight) {
                $sum += (int) $cnpj[$i] * $weight;
            }

            $rest = $sum % 11;

            if ((int) $cnpj[$position] !== ($rest < 2 ? 0 : 11 - $rest)) {
                $fail('CNPJ inválido.');

                return;
            }
        }
    }
}
