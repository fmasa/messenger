<?php

declare(strict_types=1);

namespace Fixtures;

use InvalidArgumentException;

use function sprintf;

final class HandlerWithNamedMethods
{
    private string $result;

    public function __construct(string $result)
    {
        $this->result = $result;
    }

    public function handleWithArgumentType(Message $message): ?string
    {
        return $this->result;
    }

    /**
     * @param mixed $message
     */
    public function handleWithoutArgumentType($message): ?string
    {
        if (! $message instanceof Message) {
            throw new InvalidArgumentException(sprintf(
                'Message must be an instance of %s.',
                Message::class
            ));
        }

        return $this->result;
    }
}
