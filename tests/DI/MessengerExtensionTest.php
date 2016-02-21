<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use Fixtures\Message;
use Fixtures\Stamp;
use Fmasa\Messenger\Exceptions\MultipleHandlersFound;
use Nette\Configurator;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class MessengerExtensionTest extends TestCase
{
    public function testAddingMiddleware() : void
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

    public function testExceptionIsThrownIfThereAreMultipleHandlersWhenSingleHandlerPerMessageIsTrue() : void
    {
        $this->expectException(MultipleHandlersFound::class);

        $this->getContainer(__DIR__ . '/singleHandlerPerMessage.neon');
    }

    public function testAllHandlersAreCalled() : void
    {
        $container = $this->getContainer(__DIR__ . '/multipleHandlers.neon');

        $messageBus = $container->getService('messenger.default.bus');
        assert($messageBus instanceof MessageBusInterface);

        $this->assertResultsAreSame(
            ['first result', 'second result', 'fixed result'],
            $messageBus->dispatch(new Message())
        );
    }

    public function testHandlersRestrictedToCertainBus() : void
    {
        $container = $this->getContainer(__DIR__ . '/restrictedHandlers.neon');

        $defaultBus = $container->getService('messenger.default.bus');
        $otherBus = $container->getService('messenger.other.bus');
        assert($defaultBus instanceof MessageBusInterface && $otherBus instanceof MessageBusInterface);

        $this->assertResultsAreSame(['default result'], $defaultBus->dispatch(new Message()));
        $this->assertResultsAreSame(['other result'], $otherBus->dispatch(new Message()));
    }

    private function assertResultsAreSame(array $expectedResults, Envelope $envelope) : void
    {
        $stamps = $envelope->all(HandledStamp::class);

        $this->assertSame(
            $expectedResults,
            array_map(
                function (HandledStamp $stamp) : string {
                    return $stamp->getResult();
                },
                $stamps
            )
        );
    }

    private function getContainer(string $configFile) : Container
    {
        $configurator = new Configurator();
        $configurator->setTempDirectory(__DIR__ . '/../temp');
        $configurator->setDebugMode(true);

        $robotLoader = $configurator->createRobotLoader();
        $robotLoader->addDirectory(__DIR__ . '/../fixtures');
        $robotLoader->register();

        $configurator->addConfig(__DIR__ . '/base.neon');
        $configurator->addConfig($configFile);

        return $configurator->createContainer();
    }
}
