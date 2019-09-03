<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use Fixtures\Message;
use Fixtures\Stamp;
use Fmasa\Messenger\Exceptions\InvalidHandlerService;
use Fmasa\Messenger\Exceptions\MultipleHandlersFound;
use Fmasa\Messenger\Tracy\MessengerPanel;
use Nette\Configurator;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use function array_map;
use function assert;

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

    /**
     * @param string[] $expectedResults
     *
     * @dataProvider dataMultipleHandlers
     */
    public function testMultipleHandlersAreCalled(string $configFiles, array $expectedResults) : void
    {
        $container = $this->getContainer($configFiles);

        $messageBus = $container->getService('messenger.default.bus');
        assert($messageBus instanceof MessageBusInterface);

        $this->assertResultsAreSame(
            $expectedResults,
            $messageBus->dispatch(new Message())
        );
    }

    /**
     * @return (string|string[])[][]
     */
    public static function dataMultipleHandlers() : array
    {
        return [
            [__DIR__ . '/multipleHandlers.neon', ['first result', 'second result', 'fixed result']],
            [__DIR__ . '/multipleHandlersWithAliases.neon', ['first result', 'second result', 'fixed result']],
            [__DIR__ . '/multipleHandlersWithSameAlias.neon', ['first result', 'fixed result']],
        ];
    }

    public function testHandlersRestrictedToCertainBus() : void
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
    public function testInvalidHandlersThrowException(string $configFile) : void
    {
        $this->expectException(InvalidHandlerService::class);

        $this->getContainer($configFile);
    }

    /**
     * @return string[][]
     */
    public function dataInvalidHandlerConfigs() : array
    {
        return [
            [__DIR__ . '/invalidHandler.invalidArgumentType.neon'],
            [__DIR__ . '/invalidHandler.withoutArguments.neon'],
            [__DIR__ . '/invalidHandler.withoutArgumentType.neon'],
            [__DIR__ . '/invalidHandler.withoutInvokeMethod.neon'],
            [__DIR__ . '/invalidHandler.withTooManyArguments.neon'],
        ];
    }

    public function testTracyPanel() : void
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

    /**
     * @param string[] $expectedResults
     */
    private function assertResultsAreSame(array $expectedResults, Envelope $envelope) : void
    {
        $stamps = $envelope->all(HandledStamp::class);

        $this->assertSame(
            $expectedResults,
            array_map(
                static function (HandledStamp $stamp) : string {
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
