<?php

declare(strict_types=1);

namespace Fmasa\Messenger;

use Fmasa\Messenger\DI\HandlerDefinition;
use Nette\DI\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

use function assert;
use function get_class;
use function is_callable;

/**
 * @internal
 */
final class LazyHandlersLocator implements HandlersLocatorInterface
{
    /** @var array<string, iterable<HandlerDefinition>> */
    private array $handlerDefinitions;

    private Container $container;

    /**
     * @param array<string, iterable<HandlerDefinition>> $handlerDefinitions
     */
    public function __construct(array $handlerDefinitions, Container $container)
    {
        $this->handlerDefinitions = $handlerDefinitions;
        $this->container          = $container;
    }

    /**
     * @return HandlerDescriptor[]
     */
    public function getHandlers(Envelope $envelope): iterable
    {
        $handlers = [];

        foreach ($this->handlerDefinitions[get_class($envelope->getMessage())] ?? [] as $handlerDefinition) {
            $service = $this->container->getService($handlerDefinition->serviceName);
            $handler = [$service, $handlerDefinition->methodName];
            assert(is_callable($handler));

            $options            = $handlerDefinition->options;
            $options['alias'] ??= $handlerDefinition->serviceName;

            $handlerDescriptor = new HandlerDescriptor($handler, $options);

            if (! $this->shouldHandle($envelope, $handlerDescriptor)) {
                continue;
            }

            $handlers[] = $handlerDescriptor;
        }

        return $handlers;
    }

    private function shouldHandle(Envelope $envelope, HandlerDescriptor $handlerDescriptor): bool
    {
        $received = $envelope->last(ReceivedStamp::class);

        if (! $received instanceof ReceivedStamp) {
            return true;
        }

        $expectedTransport = $handlerDescriptor->getOption('from_transport');

        if ($expectedTransport === null) {
            return true;
        }

        return $received->getTransportName() === $expectedTransport;
    }
}
