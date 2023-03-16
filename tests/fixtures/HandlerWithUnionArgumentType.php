<?php

declare(strict_types=1);

namespace Fixtures;

final class HandlerWithUnionArgumentType
{
    private bool $called = false;

    private ?string $result;

    public function __construct(?string $result = null)
    {
        $this->result = $result;
    }

    public function __invoke(Message|Message2 $message): ?string
    {
        $this->called = true;

        return $this->result;
    }

    public function isCalled(): bool
    {
        return $this->called;
    }
}
