<?php

declare(strict_types=1);

namespace Fixtures;

class Handler
{
    /** @var bool */
    private $called = false;

    /** @var string|null */
    private $result;

    public function __construct(?string $result = null)
    {
        $this->result = $result;
    }

    public function __invoke(Message $message) : ?string
    {
        $this->called = true;

        return $this->result;
    }

    public function isCalled() : bool
    {
        return $this->called;
    }
}
