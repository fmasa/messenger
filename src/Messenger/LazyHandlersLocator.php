<?php

declare(strict_types=1);

namespace Fmasa\Messenger;

use Nette\DI\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use function assert;
use function get_class;
use function is_callable;

/**
 * @internal
 */
final class LazyHandlersLocator implements HandlersLocatorInterface
{
    /** @var array<string, array<string, string|null>> message type => [handler service name => alias] */
    private $handlersMap;

    /** @var Container */
    private $container;

    /**
     * @param array<string, array<string, string|null>> $handlersMap
     */
    public function __construct(array $handlersMap, Container $container)
    {
        $this->handlersMap = $handlersMap;
        $this->container   = $container;
    }

    /**
     * @return HandlerDescriptor[]
     */
    public function getHandlers(Envelope $envelope) : iterable
    {
        $handlers = [];

        foreach ($this->handlersMap[get_class($envelope->getMessage())] ?? [] as $serviceName => $alias) {
            $service = $this->container->getService($serviceName);

            assert(is_callable($service));

            $handlers[] = new HandlerDescriptor($service, ['alias' => $alias ?? $serviceName]);
        }

        return $handlers;
    }
}
