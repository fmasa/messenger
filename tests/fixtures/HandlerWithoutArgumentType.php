<?php

declare(strict_types=1);

namespace Fixtures;

final class HandlerWithoutArgumentType
{
    public function __invoke($message) : void
    {
    }
}
