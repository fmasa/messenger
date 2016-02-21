<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use function assert;
use function call_user_func;
use function count;
use Fmasa\Messenger\Exceptions\InvalidHandlerService;
use Fmasa\Messenger\Exceptions\MultipleHandlersFound;
use Fmasa\Messenger\LazyHandlersLocator;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\DI\CompilerExtension;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

class MessengerExtension extends CompilerExtension
{
    private const TAG_HANDLER = 'messenger.messageHandler';
    private const HANDLERS_LOCATOR_SERVICE_NAME = '.handlersLocator';

    public function getConfigSchema() : Schema
    {
        return Expect::structure([
            'buses' => Expect::arrayOf(Expect::from(new BusConfig())),
        ]);
    }

    public function loadConfiguration() : void
    {
        $builder = $this->getContainerBuilder();

        foreach ($this->getConfig()->buses as $busName => $busConfig) {
            assert($busConfig instanceof BusConfig);

            $middleware = [];

            foreach ($busConfig->middleware as $index => $middlewareDefinition) {
                $middleware[] = $builder->addDefinition($this->prefix($busName . '.middleware.' . $index))
                    ->setFactory($middlewareDefinition);
            }

            $handlersLocator = $builder->addDefinition($this->prefix($busName . self::HANDLERS_LOCATOR_SERVICE_NAME))
                ->setFactory(LazyHandlersLocator::class);

            $middleware[] = $builder->addDefinition($this->prefix($busName . '.defaultMiddleware'))
                ->setFactory(HandleMessageMiddleware::class, [$handlersLocator, $busConfig->allowNoHandlers]);

            $builder->addDefinition($this->prefix($busName . '.bus'))
                ->setFactory(MessageBus::class, [$middleware]);
        }
    }

    /**
     * @throws InvalidHandlerService
     * @throws MultipleHandlersFound
     */
    public function beforeCompile() : void
    {
        $config = $this->getConfig();
        $builder = $this->getContainerBuilder();

        foreach ($config->buses as $busName => $busConfig) {
            assert($busConfig instanceof BusConfig);

            $handlers = [];

            foreach ($this->getHandlersForBus($busName) as $serviceName) {
                foreach($this->getHandledMessageNames($serviceName) as $messageName) {
                    if (! isset($handlers[$messageName])) {
                        $handlers[$messageName] = [];
                    }

                    $alias = $builder->getDefinition($serviceName)->getTag(self::TAG_HANDLER);

                    $handlers[$messageName][$serviceName] = $alias['alias'] ?? null;
                }
            }

            if ($busConfig->singleHandlerPerMessage) {
                foreach ($handlers as $messageName => $messageHandlers) {
                    if (count($messageHandlers) > 1) {
                        throw MultipleHandlersFound::fromHandlerClasses(
                            $messageName,
                            array_map([$builder, 'getDefinition'], array_keys($messageHandlers))
                        );
                    }
                }
            }

            $handlersLocator = $this->getContainerBuilder()
                ->getDefinition($this->prefix($busName . self::HANDLERS_LOCATOR_SERVICE_NAME));

            assert($handlersLocator instanceof ServiceDefinition);

            $handlersLocator->setArguments([$handlers]);
        }
    }

    /**
     * @return string[] Service names
     */
    private function getHandlersForBus(string $busName) : array
    {
        $builder = $this->getContainerBuilder();

        $serviceNames = array_keys(
            array_merge(
                $builder->findByTag(self::TAG_HANDLER),
                $builder->findByType(MessageHandlerInterface::class)
            )
        );

        return array_filter(
            $serviceNames,
            function (string $serviceName) use ($builder, $busName) : bool {
                $definition = $builder->getDefinition($serviceName);

                return ($definition->getTag(self::TAG_HANDLER)['bus'] ?? $busName) === $busName;
            }
        );
    }

    /**
     * @throws InvalidHandlerService
     *
     * @return iterable<string>
     */
    private function getHandledMessageNames(string $serviceName) : iterable
    {
        $handlerClass = new ReflectionClass($this->getContainerBuilder()->getDefinition($serviceName)->getType());

        if ($handlerClass->implementsInterface(MessageSubscriberInterface::class)) {
            return call_user_func([$handlerClass->getName(), 'getHandledMessages']);
        }

        try {
            $method = $handlerClass->getMethod('__invoke');
        } catch (ReflectionException $e) {
            throw InvalidHandlerService::missingInvokeMethod($serviceName, $handlerClass->getName());
        }

        if ($method->getNumberOfRequiredParameters() !== 1) {
            throw InvalidHandlerService::wrongAmountOfArguments($serviceName, $handlerClass->getName());
        }

        $parameter = $method->getParameters()[0];
        $type = $parameter->getType();

        if ($type === null) {
            throw InvalidHandlerService::missingArgumentType($serviceName, $parameter);
        }

        if ($type->isBuiltin()) {
            throw InvalidHandlerService::invalidArgumentType($serviceName, $parameter);
        }

        return [$type->getName()];
    }
}
