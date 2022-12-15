<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use Fmasa\Messenger\Exceptions\InvalidHandlerService;
use Fmasa\Messenger\Exceptions\MultipleHandlersFound;
use Fmasa\Messenger\LazyHandlersLocator;
use Fmasa\Messenger\Tracy\LogToPanelMiddleware;
use Fmasa\Messenger\Tracy\MessengerPanel;
use Fmasa\Messenger\Transport\SendersLocator;
use Fmasa\Messenger\Transport\TaggedServiceLocator;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnSigtermSignalListener;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function array_fill_keys;
use function array_keys;
use function array_map;
use function array_merge;
use function assert;
use function class_exists;
use function count;
use function is_callable;
use function is_int;
use function is_string;
use function krsort;

class MessengerExtension extends CompilerExtension
{
    private const TAG_HANDLER           = 'messenger.messageHandler';
    private const TAG_TRANSPORT_FACTORY = 'messenger.transportFactory';
    private const TAG_RECEIVER_ALIAS    = 'messenger.receiver.alias';
    private const TAG_BUS_NAME          = 'messenger.bus.name';
    private const TAG_RETRY_STRATEGY    = 'messenger.retryStrategy';
    private const TAG_FAILURE_TRANSPORT = 'messenger.failureTransport';

    private const HANDLERS_LOCATOR_SERVICE_NAME = '.handlersLocator';
    private const PANEL_MIDDLEWARE_SERVICE_NAME = '.middleware.panel';
    private const PANEL_SERVICE_NAME            = 'panel';

    private const DEFAULT_FACTORIES = [
        'amqp' => AmqpTransportFactory::class,
        'inMemory' => InMemoryTransportFactory::class,
        'redis' => RedisTransportFactory::class,
    ];

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'serializer' => Expect::from(new SerializerConfig()),
            'buses' => Expect::arrayOf(Expect::from(new BusConfig())),
            'transports' => Expect::arrayOf(Expect::anyOf(
                Expect::string(),
                Expect::from(new TransportConfig())
            )),
            'failureTransport' => Expect::string()->nullable(),
            'routing' => Expect::arrayOf(
                Expect::anyOf(Expect::string(), Expect::listOf(Expect::string()))
            ),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();

        $this->compiler->addExportedTag(SendersLocator::TAG_SENDER_ALIAS);
        $this->compiler->addExportedTag(self::TAG_RETRY_STRATEGY);
        $this->compiler->addExportedTag(self::TAG_FAILURE_TRANSPORT);
        $this->compiler->addExportedTag(self::TAG_RECEIVER_ALIAS);
        $this->compiler->addExportedTag(self::TAG_BUS_NAME);

        $this->processTransports();
        $this->processRouting();
        $this->processBuses();
        $this->processConsoleCommands();

        if (! $this->isPanelEnabled()) {
            return;
        }

        $builder->addDefinition($this->prefix(self::PANEL_SERVICE_NAME))
            ->setType(MessengerPanel::class)
            ->setArguments([$this->getContainerBuilder()->findByType(LogToPanelMiddleware::class)]);
    }

    /**
     * @throws InvalidHandlerService
     * @throws MultipleHandlersFound
     */
    public function beforeCompile(): void
    {
        $config  = $this->getConfig();
        $builder = $this->getContainerBuilder();

        foreach ($config->buses as $busName => $busConfig) {
            assert($busConfig instanceof BusConfig);

            $handlers = [];

            foreach ($this->getHandlerDefinitionsForBus($busName) as $messageName => $handlerDefinitions) {
                foreach ($handlerDefinitions as $handlerDefinition) {
                    $handlers[$messageName][$handlerDefinition->serviceName] = $handlerDefinition;
                }
            }

            if ($busConfig->singleHandlerPerMessage) {
                foreach ($handlers as $messageName => $handlerDefinitions) {
                    if (count($handlerDefinitions) > 1) {
                        throw MultipleHandlersFound::fromHandlerClasses(
                            $messageName,
                            array_map([$builder, 'getDefinition'], array_keys($handlerDefinitions))
                        );
                    }
                }
            }

            $handlersLocator = $this->getContainerBuilder()
                ->getDefinition($this->prefix($busName . self::HANDLERS_LOCATOR_SERVICE_NAME));

            assert($handlersLocator instanceof ServiceDefinition);

            $handlersLocator->setArguments([$handlers]);
        }

        $this->setupEventDispatcher();
        $this->passRegisteredTransportFactoriesToMainFactory();
    }

    public function afterCompile(ClassType $class): void
    {
        if (! $this->isPanelEnabled()) {
            return;
        }

        $this->enableTracyIntegration($class);
    }

    private function processBuses(): void
    {
        $builder = $this->getContainerBuilder();

        foreach ($this->getConfig()->buses as $busName => $busConfig) {
            assert($busConfig instanceof BusConfig);

            $middleware = [];

            if ($busConfig->panel) {
                $middleware[] = $builder->addDefinition($this->prefix($busName . self::PANEL_MIDDLEWARE_SERVICE_NAME))
                    ->setFactory(LogToPanelMiddleware::class, [$busName]);
            }

            foreach ($busConfig->middleware as $index => $middlewareDefinition) {
                $middleware[] = $builder->addDefinition($this->prefix($busName . '.middleware.' . $index))
                    ->setFactory($middlewareDefinition);
            }

            $handlersLocator = $builder->addDefinition($this->prefix($busName . self::HANDLERS_LOCATOR_SERVICE_NAME))
                ->setFactory(LazyHandlersLocator::class);

            $middleware[] = $builder->addDefinition($this->prefix($busName . '.sendMiddleware'))
                ->setFactory(SendMessageMiddleware::class);

            $middleware[] = $builder->addDefinition($this->prefix($busName . '.defaultMiddleware'))
                ->setFactory(HandleMessageMiddleware::class, [$handlersLocator, $busConfig->allowNoHandlers]);

            $builder->addDefinition($this->prefix($busName . '.bus'))
                ->setFactory(MessageBus::class, [$middleware])
                ->setTags([self::TAG_BUS_NAME => $busName]);
        }
    }

    /**
     * @return Statement[]
     */
    private function getSubscribers(): array
    {
        return [
            new Statement(DispatchPcntlSignalListener::class),
            new Statement(
                SendFailedMessageForRetryListener::class,
                [
                    new Statement(TaggedServiceLocator::class, [SendersLocator::TAG_SENDER_ALIAS]),
                    new Statement(TaggedServiceLocator::class, [self::TAG_RETRY_STRATEGY]),
                ]
            ),
            new Statement(
                SendFailedMessageToFailureTransportListener::class,
                [new Statement(TaggedServiceLocator::class, [self::TAG_FAILURE_TRANSPORT])]
            ),
            new Statement(StopWorkerOnSigtermSignalListener::class),
        ];
    }

    private function processConsoleCommands(): void
    {
        $builder = $this->getContainerBuilder();

        $routableBus = $builder->addDefinition($this->prefix('busLocator'))
            ->setAutowired(false)
            ->setFactory(
                RoutableMessageBus::class,
                [new Statement(TaggedServiceLocator::class, [self::TAG_BUS_NAME]), null]
            );

        $receiverLocator = $builder->addDefinition($this->prefix('console.receiversLocator'))
            ->setFactory(TaggedServiceLocator::class, [self::TAG_RECEIVER_ALIAS])
            ->setAutowired(false);

        $builder->addDefinition($this->prefix('console.command.consumeMessages'))
            ->setFactory(ConsumeMessagesCommand::class, [$routableBus, $receiverLocator]);
    }

    private function processTransports(): void
    {
        $builder = $this->getContainerBuilder();

        $transportFactory = $builder->addDefinition($this->prefix('transportFactory'))
            ->setFactory(TransportFactory::class);

        foreach (self::DEFAULT_FACTORIES as $name => $factoryClass) {
            $builder->addDefinition($this->prefix('transportFactory.' . $name))
                ->setFactory($factoryClass)
                ->setTags([self::TAG_TRANSPORT_FACTORY => true]);
        }

        $serializerConfig = $this->getConfig()->serializer;
        assert($serializerConfig instanceof SerializerConfig);

        $defaultSerializer = $builder->addDefinition($this->prefix('defaultSerializer'))
            ->setType(SerializerInterface::class)
            ->setFactory($serializerConfig->defaultSerializer);

        $failureTransports = [];

        foreach ($this->getConfig()->transports as $transportName => $transportConfig) {
            assert(is_string($transportConfig) || $transportConfig instanceof TransportConfig);

            $failureTransportName = $transportConfig->failureTransport ?? $this->getConfig()->failureTransport;
            if ($failureTransportName !== null) {
                $failureTransports[$failureTransportName][] = $transportName;
            }

            if (is_string($transportConfig)) {
                $dsn        = $transportConfig;
                $options    = [];
                $serializer = $defaultSerializer;
            } else {
                $dsn        = $transportConfig->dsn;
                $options    = $transportConfig->options;
                $serializer = $transportConfig->serializer !== null
                    ? $builder->addDefinition($this->prefix('serializer.' . $transportName))
                        ->setType(SerializerInterface::class)
                        ->setFactory($transportConfig->serializer)
                    : $defaultSerializer;
            }

            $transportServiceName = $this->prefix('transport.' . $transportName);

            $builder->addDefinition($transportServiceName)
                ->setFactory([$transportFactory, 'createTransport'], [$dsn, $options, $serializer])
                ->setTags([
                    SendersLocator::TAG_SENDER_ALIAS => $transportName,
                    self::TAG_RECEIVER_ALIAS => $transportName,
                ]);
        }

        foreach ($failureTransports as $failureTransportName => $transportNames) {
            $builder->getDefinition($this->prefix('transport.' . $failureTransportName))
                ->addTag(self::TAG_FAILURE_TRANSPORT, $transportNames);
        }
    }

    private function processRouting(): void
    {
        $this->getContainerBuilder()->addDefinition($this->prefix('sendersLocator'))
            ->setFactory(
                SendersLocator::class,
                [
                    array_map(
                        static function ($oneOrManyTransports): array {
                                return is_string($oneOrManyTransports) ? [$oneOrManyTransports] : $oneOrManyTransports;
                        },
                        $this->getConfig()->routing
                    ),
                ]
            );
    }

    /**
     * @return iterable<string, iterable<HandlerDefinition>>
     *
     * @throws InvalidHandlerService
     */
    private function getHandlerDefinitionsForBus(string $busName): iterable
    {
        $builder                     = $this->getContainerBuilder();
        $handlerDefinitionsByMessage = [];

        /** @var string[] $serviceNames */
        $serviceNames = array_keys(
            array_merge(
                $builder->findByTag(self::TAG_HANDLER),
                $builder->findByType(MessageHandlerInterface::class)
            )
        );

        foreach ($serviceNames as $serviceName) {
            $serviceDefinition = $builder->getDefinition($serviceName);
            $handlerClassName  = $serviceDefinition->getType();
            $tag               = $serviceDefinition->getTag(self::TAG_HANDLER);
            $alias             = $tag['alias'] ?? null;
            assert(class_exists($handlerClassName));

            if ($busName !== ($tag['bus'] ?? $busName)) {
                continue;
            }

            $handlerReflection = new ReflectionClass($handlerClassName);

            if (isset($tag['handles'])) {
                $handles = isset($tag['method']) ? [$tag['handles'] => $tag['method']] : [$tag['handles']];
            } else {
                $handles = $this->guessHandledClasses($handlerReflection, $serviceName, $tag['method'] ?? '__invoke');
            }

            foreach ($handles as $message => $options) {
                if (is_int($message)) {
                    $message = (string) $options;
                    $options = [];
                }

                if (is_string($options)) {
                    $options = ['method' => $options];
                }

                if (isset($options['bus']) && $options['bus'] !== $busName) {
                    continue;
                }

                if (! isset($options['from_transport']) && isset($tag['from_transport'])) {
                    $options['from_transport'] = $tag['from_transport'];
                }

                $priority = $tag['priority'] ?? $options['priority'] ?? 0;
                $method   = $options['method'] ?? '__invoke';

                if (! $handlerReflection->hasMethod($method)) {
                    throw InvalidHandlerService::missingHandlerMethod($serviceName, $handlerClassName, $method);
                }

                if ($alias !== null) {
                    $options['alias'] = $alias;
                }

                $handlerDefinitionsByMessage[(string) $message][$priority][] = new HandlerDefinition($serviceName, $method, $options);
            }
        }

        foreach ($handlerDefinitionsByMessage as $message => $handlersByPriority) {
            krsort($handlersByPriority);
            $handlerDefinitionsByMessage[$message] = array_merge(...$handlersByPriority);
        }

        return $handlerDefinitionsByMessage;
    }

    /**
     * @param ReflectionClass<object> $handlerReflection
     *
     * @return iterable<string>
     *
     * @throws InvalidHandlerService
     */
    private function guessHandledClasses(ReflectionClass $handlerReflection, string $serviceName, string $methodName): iterable
    {
        $handlerClassName = $handlerReflection->getName();

        if ($handlerReflection->implementsInterface(MessageSubscriberInterface::class)) {
            $getHandledMessages = [$handlerClassName, 'getHandledMessages'];

            if (is_callable($getHandledMessages)) {
                return $getHandledMessages();
            }
        }

        try {
            $method = $handlerReflection->getMethod($methodName);
        } catch (ReflectionException $e) {
            throw InvalidHandlerService::missingHandlerMethod($serviceName, $handlerClassName, $methodName);
        }

        if ($method->getNumberOfRequiredParameters() !== 1) {
            throw InvalidHandlerService::wrongAmountOfArguments($serviceName, $handlerClassName, $methodName);
        }

        $parameter     = $method->getParameters()[0];
        $parameterName = $parameter->getName();
        $type          = $parameter->getType();
        assert($type instanceof ReflectionNamedType || $type instanceof ReflectionUnionType || $type === null);

        if ($type === null) {
            throw InvalidHandlerService::missingArgumentType($serviceName, $handlerClassName, $methodName, $parameterName);
        }

        if ($type instanceof ReflectionUnionType) {
            $types        = [];
            $invalidTypes = [];
            foreach ($type->getTypes() as $type) {
                if (! $type->isBuiltin()) {
                    $types[] = (string) $type;
                } else {
                    $invalidTypes[] = (string) $type;
                }
            }

            if (count($types) > 0) {
                return $methodName === '__invoke' ? $types : array_fill_keys($types, $methodName);
            }

            throw InvalidHandlerService::invalidArgumentUnionType($serviceName, $handlerClassName, $methodName, $parameterName, $invalidTypes);
        }

        if ($type->isBuiltin()) {
            throw InvalidHandlerService::invalidArgumentType($serviceName, $handlerClassName, $methodName, $parameterName, $type);
        }

        return $methodName === '__invoke' ? [$type->getName()] : [$type->getName() => $methodName];
    }

    private function enableTracyIntegration(ClassType $class): void
    {
        $class->getMethod('initialize')->addBody($this->getContainerBuilder()->formatPhp('?;', [
            new Statement(
                '@Tracy\Bar::addPanel',
                [new Statement('@' . $this->prefix(self::PANEL_SERVICE_NAME))]
            ),
        ]));
    }

    private function isPanelEnabled(): bool
    {
        return $this->getContainerBuilder()->findByType(LogToPanelMiddleware::class) !== [];
    }

    private function setupEventDispatcher(): void
    {
        $builder = $this->getContainerBuilder();

        $eventDispatcherServiceName = $builder->getByType(EventDispatcherInterface::class);

        if ($eventDispatcherServiceName === null) {
            $eventDispatcher = $builder->addDefinition($this->prefix('console.eventDispatcher'))
                ->setFactory(EventDispatcher::class)
                ->setAutowired(false);

            $consumeMessagesCommand = $builder->getDefinition($this->prefix('console.command.consumeMessages'));
            assert($consumeMessagesCommand instanceof ServiceDefinition);

            if (! isset($consumeMessagesCommand->getFactory()->arguments[2])) {
                $consumeMessagesCommand->getFactory()->arguments[2] = $eventDispatcher;
            }
        } else {
            $eventDispatcher = $builder->getDefinition($eventDispatcherServiceName);
        }

        assert($eventDispatcher instanceof ServiceDefinition);

        foreach ($this->getSubscribers() as $subscriber) {
            $eventDispatcher->addSetup('addSubscriber', [$subscriber]);
        }
    }

    private function passRegisteredTransportFactoriesToMainFactory(): void
    {
        $builder = $this->getContainerBuilder();

        $transportFactory = $builder->getDefinition($this->prefix('transportFactory'));
        assert($transportFactory instanceof ServiceDefinition);

        $transportFactory->setArguments([
            array_map([$builder, 'getDefinition'], array_keys($builder->findByTag(self::TAG_TRANSPORT_FACTORY))),
        ]);
    }
}
