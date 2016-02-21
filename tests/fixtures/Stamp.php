<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class Stamp implements StampInterface
{
    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue() : string
    {
        return $this->value;
    }
}
