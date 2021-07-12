<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Transport;

use Fmasa\Messenger\Exceptions\ServiceNotFound;
use Nette\DI\Container;
use Psr\Container\ContainerInterface;

use function in_array;
use function is_array;

// ContainerInterface does not use typehints, so we cannot add them without breaking LSP
// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint

final class TaggedServiceLocator implements ContainerInterface
{
    private string $tagName;

    private Container $container;

    private ?string $defaultServiceName = null;

    public function __construct(string $tagName, Container $container, ?string $defaultServiceName = null)
    {
        $this->tagName            = $tagName;
        $this->container          = $container;
        $this->defaultServiceName = $defaultServiceName;
    }

    /**
     * @var string $id
     */
    public function get($id): object
    {
        foreach ($this->container->findByTag($this->tagName) as $serviceName => $receiverName) {
            if (
                $receiverName === $id
                || (is_array($receiverName) && in_array($id, $receiverName, true))
            ) {
                return $this->container->getService($serviceName);
            }
        }

        if ($this->defaultServiceName !== null) {
            return $this->container->getService($this->defaultServiceName);
        }

        throw ServiceNotFound::withTag($this->tagName, $id);
    }

    /**
     * @var string $id
     */
    public function has($id): bool
    {
        foreach ($this->container->findByTag($this->tagName) as $receiverName) {
            if (
                $receiverName === $id
                || (is_array($receiverName) && in_array($id, $receiverName, true))
            ) {
                return true;
            }
        }

        return $this->defaultServiceName !== null;
    }
}
