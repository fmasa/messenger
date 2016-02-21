<?php

declare(strict_types=1);

namespace Fixtures;

final class FixedHandler
{
    public function __invoke(Message $message) : string
    {
        return 'fixed result';
    }
}
