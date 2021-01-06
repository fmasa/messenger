<?php

declare(strict_types=1);

namespace Fixtures;

final class HandlerWithTooManyArguments
{
    public function __invoke(Message $first, Message $second): void
    {
    }
}
