<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Transport;

use Fmasa\Messenger\Exceptions\ServiceNotFound;
use Nette\DI\Container;
use Psr\Container\ContainerInterface;

// ContainerInterface does not use typehints, so we cannot add them without breaking LSP
// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration

final class TaggedServiceLocator implements ContainerInterface
{
    /** @var string */
    private $tagName;

    /** @var Container */
    private $container;

    /** @var string|null */
    private $defaultServiceName;

    public function __construct(string $tagName, Container $container, ?string $defaultServiceName = null)
    {
        $this->tagName            = $tagName;
        $this->container          = $container;
        $this->defaultServiceName = $defaultServiceName;
    }

    /**
     * @return object
     */
    public function get($id)
    {
        foreach ($this->container->findByTag($this->tagName) as $serviceName => $receiverName) {
            if ($receiverName === $id) {
                return $this->container->getService($serviceName);
            }
        }

        if ($this->defaultServiceName !== null) {
            return $this->container->getService($this->defaultServiceName);
        }

        throw ServiceNotFound::withTag($this->tagName, $id);
    }

    /**
     * @param string $id
     */
    public function has($id) : bool
    {
        foreach ($this->container->findByTag($this->tagName) as $receiverName) {
            if ($receiverName === $id) {
                return true;
            }
        }

        return $this->defaultServiceName === null;
    }
}
