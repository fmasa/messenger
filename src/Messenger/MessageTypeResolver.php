<?php

declare(strict_types=1);

namespace Fmasa\Messenger;

use Symfony\Component\Messenger\Envelope;
use function class_implements;
use function class_parents;
use function get_class;

/**
 * @internal This class is not part of public API and can be changed between versions
 */
final class MessageTypeResolver
{
    /**
     * @return string[]
     */
    public static function listTypes(Envelope $envelope) : array
    {
        $class = get_class($envelope->getMessage());

        return [$class => $class]
            + (class_parents($class) ?: [])
            + (class_implements($class) ?: [])
            + ['*' => '*'];
    }
}
