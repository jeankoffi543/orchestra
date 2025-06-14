<?php

namespace Kjos\Orchestra\Rules;

use Illuminate\Contracts\Validation\Rule;

class PassWordRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        return (bool) \preg_match('/^[a-zA-Z0-9!@#%^&*_+=\-?]{6,64}$/', $value);
    }

    public function message(): string
    {
        return 'Le champ :attribute doit être un mot de passe valide.';
    }
}
