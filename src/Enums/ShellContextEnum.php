<?php

namespace Kjos\Orchestra\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

/**
 * @method static string ENV()
 * @method static string ARG()
 * @method static string FILE()
 */
enum ShellContextEnum: string
{
    use Values;
    use InvokableCases;

    case ENV  = 'env';
    case ARG  = 'arg';
    case FILE = 'file';
}
