<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

use function sprintf;

final class ServiceNotFound extends Exception implements NotFoundExceptionInterface
{
    public static function withTag(string $tagName, string $tagValue): self
    {
        return new self(sprintf('Service with tag "%s" = "%s" was not found', $tagName, $tagValue));
    }
}
