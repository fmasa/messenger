<?php

declare(strict_types=1);

namespace Fixtures;

final class HandlerWithInvalidArgumentType
{
    public function __invoke(int $message) : void
    {
    }
}
