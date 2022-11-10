<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use Exception;
use Fixtures\CustomTransport;
use Fixtures\CustomTransportFactory;
use Fixtures\DummySerializer;
use Fixtures\Message;
use Fixtures\Message2;
use Fixtures\Message3;
use Fixtures\MessageImplementingInterface;
use Fixtures\Stamp;
use Fmasa\Messenger\Exceptions\InvalidHandlerService;
use Fmasa\Messenger\Exceptions\MultipleHandlersFound;
use Fmasa\Messenger\Tracy\LogToPanelMiddleware;
use Fmasa\Messenger\Tracy\MessengerPanel;
use Nette\Configurator;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

use function array_map;
use function assert;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class MessengerExtensionTest extends TestCase
{
    public function testAddingMiddleware(): void
    {
        $container = $this->getContainer(__DIR__ . '/middlewares.neon');

        $messageBus = $container->getService('messenger.default.bus');
        assert($messageBus instanceof MessageBusInterface);

        $envelope = $messageBus->dispatch(new Message());
        /** @var Stamp[] $stamps */
        $stamps = $envelope->all(Stamp::class);

        $this->assertCount(2, $stamps);
        $this->assertSame('first', $stamps[0]->getValue());
        $this->assertSame('second', $stamps[1]->getValue());
    }

    public function testExceptionIsThrownIfThereAreMultipleHandlersWhenSingleHandlerPerMessageIsTrue(): void
    {
        $this->expectException(MultipleHandlersFound::class);

        $this->getContainer(__DIR__ . '/singleHandlerPerMessage.neon');
    }

    /**
     * @param string[] $expectedResults
     *
     * @dataProvider dataMultipleHandlers
     */
    public function testMultipleHandlersAreCalled(string $configFiles, array $expectedResults): void
    {
        $container = $this->getContainer($configFiles);

        $messageBus = $container->getService('messenger.default.bus');
        assert($messageBus instanceof MessageBusInterface);

        $this->assertResultsAreSame(
            $expectedResults,
            $messageBus->dispatch(new Message())
        );
    }

    public function testSingleHandlerHandlesMultipleMessages(): void
    {
        $container = $this->getContainer(__DIR__ . '/messageSubscribers.neon');

        $messageBus = $container->getService('messenger.default.bus');
        assert($messageBus instanceof MessageBusInterface);

        $this->assertResultsAreSame(
            ['message result', 'result from callable'],
            $messageBus->dispatch(new Message())
        );

        $this->assertResultsAreSame(
            ['message2 result'],
            $messageBus->dispatch(new Message2())
        );
    }

    public function testMessageSubscribersWithBusOption(): void
    {
        $container = $this->getContainer(__DIR__ . '/messageSubscribers.withBusOption.neon');

        $defaultBus = $container->getService('messenger.default.bus');
        $otherBus   = $container->getService('messenger.other.bus');
        assert($defaultBus instanceof MessageBusInterface && $otherBus instanceof MessageBusInterface);

        $this->assertResultsAreSame(
            ['result from callable'],
            $defaultBus->dispatch(new Message())
        );

        $this->assertResultsAreSame(
            ['result from callable', 'message with bus option result'],
            $otherBus->dispatch(new Message())
        );
    }

    public function testMessageSubscribersWithPriorityOption(): void
    {
        $container = $this->getContainer(__DIR__ . '/messageSubscribers.withPriorityOption.neon');

        $defaultBus = $container->getService('messenger.default.bus');
        assert($defaultBus instanceof MessageBusInterface);

        $this->assertResultsAreSame(
            ['message with higher priority result', 'result from callable'],
            $defaultBus->dispatch(new Message())
        );
    }

    /**
     * @param array<string> $transportNames
     * @param array<string> $resultMessages
     *
     * @dataProvider dataHandlersWithFromTransportOption
     */
    public function testHandlersWithFromTransportOption(string $configFile, array $transportNames, array $resultMessages): void
    {
        $container = $this->getContainer($configFile);

        $defaultBus      = $container->getService('messenger.default.bus');
        $handlersLocator = $container->getService('messenger.default.handlersLocator');
        assert($defaultBus instanceof MessageBusInterface && $handlersLocator instanceof HandlersLocatorInterface);

        $envelope = $defaultBus->dispatch(new Message());

        $this->assertResultsAreSame([], $envelope);

        $this->assertSame(
            $transportNames,
            array_map(static fn (SentStamp $stamp): string => $stamp->getSenderAlias(), $envelope->all(SentStamp::class))
        );

        $results = [];

        foreach ($handlersLocator->getHandlers($envelope) as $handlerDescriptor) {
            $results[] = $handlerDescriptor->getHandler()($envelope->getMessage());
        }

        $this->assertSame($resultMessages, $results);
    }

    /**
     * @return (string|string[])[][]
     */
    public static function dataMultipleHandlers(): array
    {
        return [
            [__DIR__ . '/multipleHandlers.neon', ['first result', 'second result', 'fixed result']],
            [__DIR__ . '/multipleHandlersWithAliases.neon', ['first result', 'second result', 'fixed result']],
            [__DIR__ . '/multipleHandlersWithSameAlias.neon', ['first result', 'fixed result']],
            [__DIR__ . '/multipleHandlersWithHandlesAndMethod.neon', ['result from handleWithArgumentType()', 'result from handleWithoutArgumentType()']],
            [__DIR__ . '/multipleHandlersWithPriority.neon', ['result with priority +10', 'result with the default priority', 'result with priority -10']],
        ];
    }

    /**
     * @return array<array{0: string, 1: array<string>, 2: array<string>}>
     */
    public static function dataHandlersWithFromTransportOption(): array
    {
        return [
            [__DIR__ . '/multipleHandlersWithFromTransport.neon', ['memory1', 'memory2'], ['message from memory1 transport result']],
            [__DIR__ . '/messageSubscribers.withFromTransportOption.neon', ['memory1', 'memory2'], ['message from memory1 transport result']],
        ];
    }

    public function testHandlersRestrictedToCertainBus(): void
    {
        $container = $this->getContainer(__DIR__ . '/restrictedHandlers.neon');

        $defaultBus = $container->getService('messenger.default.bus');
        $otherBus   = $container->getService('messenger.other.bus');
        assert($defaultBus instanceof MessageBusInterface && $otherBus instanceof MessageBusInterface);

        $this->assertResultsAreSame(['default result'], $defaultBus->dispatch(new Message()));
        $this->assertResultsAreSame(['other result'], $otherBus->dispatch(new Message()));
    }

    /**
     * @dataProvider dataInvalidHandlerConfigs
     */
    public function testInvalidHandlersThrowException(string $configFile): void
    {
        $this->expectException(InvalidHandlerService::class);

        $this->getContainer($configFile);
    }

    /**
     * @return string[][]
     */
    public function dataInvalidHandlerConfigs(): array
    {
        return [
            [__DIR__ . '/invalidHandler.invalidArgumentType.neon'],
            [__DIR__ . '/invalidHandler.withoutArguments.neon'],
            [__DIR__ . '/invalidHandler.withoutArgumentType.neon'],
            [__DIR__ . '/invalidHandler.withoutInvokeMethod.neon'],
            [__DIR__ . '/invalidHandler.withTooManyArguments.neon'],
        ];
    }

    public function testTracyPanel(): void
    {
        $container = $this->getContainer(__DIR__ . '/twoBusesWithTracy.neon');

        $defaultBus = $container->getService('messenger.default.bus');
        $otherBus   = $container->getService('messenger.other.bus');
        assert($defaultBus instanceof MessageBusInterface && $otherBus instanceof MessageBusInterface);

        $defaultBus->dispatch(new Message());
        $defaultBus->dispatch(new Message());

        $otherBus->dispatch(new Message());

        $panel = $container->getByType(MessengerPanel::class);

        $this->assertRegExp('~2\+1 messages~', $panel->getTab());
        $this->assertRegExp('~Handled messages~', $panel->getPanel());
    }

    public function testLogToPanelMiddlewareIsNotRegisteredIfPanelIsDisabled(): void
    {
        $container = $this->getContainer(__DIR__ . '/withoutPanel.neon');

        $this->assertNull($container->getByType(LogToPanelMiddleware::class, false));
    }

    /**
     * @param mixed    $message
     * @param string[] $transports
     *
     * @dataProvider dataMessagedRoutedToMemoryTransport
     */
    public function testMessageIsPassedToTransport($message, array $transports): void
    {
        $container = $this->getContainer(__DIR__ . '/transports.neon');

        $bus = $container->getService('messenger.default.bus');
        assert($bus instanceof MessageBusInterface);

        $this->assertSame(
            $transports,
            array_map(
                static function (SentStamp $stamp): string {
                    return $stamp->getSenderAlias();
                },
                $bus->dispatch($message)->all(SentStamp::class)
            )
        );
    }

    /**
     * @return mixed[]
     */
    public static function dataMessagedRoutedToMemoryTransport(): array
    {
        return [
            'message routed to one transport' => [new Message(), ['memory1']],
            'message routed to one transport (set as array)' => [new Message2(), ['memory1']],
            'message routed to two transports' => [new Message3(), ['memory1', 'memory2']],
            'message routed to two transports (to first via interface, to second via class name)' => [
                new MessageImplementingInterface(),
                ['memory2', 'memory1'],
            ],
        ];
    }

    public function testRegisterCustomTransport(): void
    {
        $container = $this->getContainer(__DIR__ . '/customTransport.neon');

        $bus = $container->getService('messenger.default.bus');
        assert($bus instanceof MessageBusInterface);

        $message = new Message();

        $result = $bus->dispatch($message);
        $stamp  = $result->last(SentStamp::class);
        assert($stamp instanceof SentStamp);

        $this->assertSame('test', $stamp->getSenderAlias());
        $this->assertSame([$message], $container->getByType(CustomTransport::class)->getSentMessages());
    }

    public function testDnsAndOptionsAndCustomDefaultSerializerArePassedToSender(): void
    {
        $container = $this->getContainer(__DIR__ . '/optionsPassed.neon');

        $bus = $container->getService('messenger.default.bus');
        assert($bus instanceof MessageBusInterface);

        $bus->dispatch(new Message());

        $transportFactory = $container->getByType(CustomTransportFactory::class);

        $this->assertSame('custom://foo', $transportFactory->getUsedDns());
        $this->assertSame(['foo' => 'bar'], $transportFactory->getUsedOptions());
        $this->assertInstanceOf(DummySerializer::class, $transportFactory->getUsedSerializer());
    }

    public function testCustomSerializerIsPassedToSender(): void
    {
        $container = $this->getContainer(__DIR__ . '/customSerializer.neon');

        $bus = $container->getService('messenger.default.bus');
        assert($bus instanceof MessageBusInterface);

        $bus->dispatch(new Message());
        $this->assertInstanceOf(
            DummySerializer::class,
            $container->getByType(CustomTransportFactory::class)->getUsedSerializer()
        );
    }

    public function testBusLocatorReturnsCorrectBuses(): void
    {
        $container = $this->getContainer(__DIR__ . '/busLocator.neon');

        $busLocator = $container->getService('messenger.busLocator');
        assert($busLocator instanceof RoutableMessageBus);

        foreach (['a', 'b', 'c'] as $busName) {
            $this->assertSame($container->getService('messenger.' . $busName . '.bus'), $busLocator->getMessageBus($busName));
        }
    }

    /**
     * @dataProvider dataFailureTransport
     */
    public function testFailureTransport(string $receiver, string $failureTransport): void
    {
        $container = $this->getContainer(__DIR__ . '/failureTransport.neon');

        $eventDispatcher = $container->getService('messenger.console.eventDispatcher');
        assert($eventDispatcher instanceof EventDispatcher);

        $eventDispatcher->dispatch(new WorkerMessageFailedEvent(
            new Envelope(new Message()),
            $receiver,
            new Exception()
        ));

        $transport = $container->getService('messenger.transport.' . $failureTransport);
        assert($transport instanceof InMemoryTransport);

        $envelope = $transport->getSent()[0] ?? null;
        $this->assertNotNull($envelope);

        $stamp = $envelope->last(SentToFailureTransportStamp::class);
        assert($stamp instanceof SentToFailureTransportStamp || $stamp === null);

        $this->assertNotNull($stamp);
        $this->assertSame($receiver, $stamp->getOriginalReceiverName());
    }

    public function testConsumingUsesDefaultEventDispatcher(): void
    {
        $container = $this->getContainer(__DIR__ . '/defaultEventDispatcher.neon');

        $eventDispatcher = $container->getService('messenger.console.eventDispatcher');
        assert($eventDispatcher instanceof EventDispatcher);
        $this->assertNotEmpty($eventDispatcher->getListeners());

        $consumeMessagesCommand = $container->getService('messenger.console.command.consumeMessages');
        assert($consumeMessagesCommand instanceof ConsumeMessagesCommand);

        $listenerInvoked = false;
        $eventDispatcher->addListener(
            WorkerStartedEvent::class,
            static function (WorkerStartedEvent $event) use (&$listenerInvoked): void {
                $listenerInvoked = true;
                $event->getWorker()->stop();
            }
        );

        $input = new ArrayInput([
            'receivers' => ['test'],
            '--memory-limit' => 1,
            '--sleep' => 1,
        ]);
        $consumeMessagesCommand->run($input, new NullOutput());

        $this->assertTrue($listenerInvoked);
    }

    public function testConsumingUsesAutowiredEventDispatcher(): void
    {
        $container = $this->getContainer(__DIR__ . '/autowiredEventDispatcher.neon');

        $this->assertFalse($container->hasService('messenger.console.eventDispatcher'));

        $eventDispatcher = $container->getService('eventDispatcher');
        assert($eventDispatcher instanceof EventDispatcher);
        $this->assertNotEmpty($eventDispatcher->getListeners());

        $consumeMessagesCommand = $container->getService('messenger.console.command.consumeMessages');
        assert($consumeMessagesCommand instanceof ConsumeMessagesCommand);

        $listenerInvoked = false;
        $eventDispatcher->addListener(
            WorkerStartedEvent::class,
            static function (WorkerStartedEvent $event) use (&$listenerInvoked): void {
                $listenerInvoked = true;
                $event->getWorker()->stop();
            }
        );

        $input = new ArrayInput([
            'receivers' => ['test'],
            '--memory-limit' => 1,
            '--sleep' => 1,
        ]);
        $consumeMessagesCommand->run($input, new NullOutput());

        $this->assertTrue($listenerInvoked);
    }

    /**
     * @return string[][]
     */
    public static function dataFailureTransport(): array
    {
        return [
            'global failure transport' => ['memory2', 'failed'],
            'specific failure transport' => ['memory1', 'memory2'],
        ];
    }

    /**
     * @param string[] $expectedResults
     */
    private function assertResultsAreSame(array $expectedResults, Envelope $envelope): void
    {
        $stamps = $envelope->all(HandledStamp::class);

        $this->assertSame(
            $expectedResults,
            array_map(
                static function (HandledStamp $stamp): string {
                    return $stamp->getResult();
                },
                $stamps
            )
        );
    }

    private function getContainer(string $configFile): Container
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('MessengerExtensionTest', true);
        mkdir($tempDir);

        $configurator = new Configurator();
        $configurator->setTempDirectory($tempDir);
        $configurator->setDebugMode(true);

        $configurator->addConfig(__DIR__ . '/base.neon');
        $configurator->addConfig($configFile);

        return $configurator->createContainer();
    }
}
