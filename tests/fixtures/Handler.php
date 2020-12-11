<?php

declare(strict_types=1);

namespace Fixtures;

class Handler
{
    private bool $called = false;

    private ?string $result = null;

    public function __construct(?string $result = null)
    {
        $this->result = $result;
    }

    public function __invoke(Message $message): ?string
    {
        $this->called = true;

        return $this->result;
    }

    public function isCalled(): bool
    {
        return $this->called;
    }
}
