<?php

declare(strict_types=1);

namespace Fixtures;

final class HandlerWithoutArgumentType
{
    /**
     * @param mixed $message
     */
    public function __invoke($message): void
    {
    }
}
