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

    /** @var array<string, mixed> */
    public array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $serviceName, string $methodName, array $options)
    {
        $this->serviceName = $serviceName;
        $this->methodName  = $methodName;
        $this->options     = $options;
    }
}
