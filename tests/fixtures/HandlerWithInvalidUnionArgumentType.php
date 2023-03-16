<?php

declare(strict_types=1);

namespace Fixtures;

final class HandlerWithInvalidUnionArgumentType
{
    public function __invoke(int|string $message): void
    {
    }
}
