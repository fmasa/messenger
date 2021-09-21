<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

/**
 * @internal
 */
final class HandlerDefinition
{
    public string $serviceName;

    public string $methodName;

    public ?string $alias;

    public function __construct(string $serviceName, string $methodName, ?string $alias = null)
    {
        $this->serviceName = $serviceName;
        $this->methodName  = $methodName;
        $this->alias       = $alias;
    }
}
