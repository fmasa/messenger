<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Exceptions;

use RuntimeException;

class SenderNotFound extends RuntimeException
{
    public static function withAlias(string $alias) : self
    {
        return new self(sprintf(
            'Sender with alias "%s" was not found',
            $alias
        ));
    }
}
