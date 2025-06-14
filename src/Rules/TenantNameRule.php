<?php

namespace Kjos\Orchestra\Rules;

use Illuminate\Contracts\Validation\Rule;

class TenantNameRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        $trimmed = \trim($value);

        return  (bool) \preg_match('/^[a-zA-Z0-9][a-zA-Z0-9 _-]*[a-zA-Z0-9]$/', $trimmed);
    }

    public function message(): string
    {
        return 'Le champ :attribute doit être un nom valide (lettres, chiffres, espaces, tirets et underscores autorisés — sans caractères spéciaux ni début/fin invalide).';
    }
}
