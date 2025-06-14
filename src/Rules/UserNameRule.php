<?php

namespace Kjos\Orchestra\Rules;

use Illuminate\Contracts\Validation\Rule;

class UserNameRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        return (bool) \preg_match('/^[a-zA-Z0-9_]+$/', $value);
    }

    public function message(): string
    {
        return 'Le champ :attribute doit être un nom valide.';
    }
}
