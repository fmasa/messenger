<?php

declare(strict_types=1);

namespace Fixtures;

final class FixedHandler
{
    public function __invoke(Message $message, bool $optionalArgument = true): string
    {
        return 'fixed result';
    }
}
