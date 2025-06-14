<?php

namespace Kjos\Orchestra\Rules;

use Illuminate\Contracts\Validation\Rule;

class DomainRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        return (bool) \preg_match('/^([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/', $value);
    }

    public function message(): string
    {
        return 'Le champ :attribute doit être un domaine valide.';
    }
}
