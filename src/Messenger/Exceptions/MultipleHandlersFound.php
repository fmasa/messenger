<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Exceptions;

use Exception;
use Nette\DI\Definitions\ServiceDefinition;

final class MultipleHandlersFound extends Exception
{
    /**
     * @param ServiceDefinition[] $handlers
     */
    public static function fromHandlerClasses(string $messageName, array $handlers) : self
    {
        return new self(sprintf(
            'There are multiple handlers for message "%s": %s',
            $messageName,
            implode(
                ', ',
                array_map(
                    function (ServiceDefinition $definition) : string {
                        return sprintf('%s (%s)', $definition->getName(), $definition->getType());
                    },
                    $handlers
                )
            )
        ));
    }
}
