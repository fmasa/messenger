<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Tracy;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Tracy\Debugger;

use function array_map;
use function count;
use function explode;
use function get_class;
use function implode;
use function microtime;
use function round;

final class LogToPanelMiddleware implements MiddlewareInterface
{
    private string $busName;

    /** @var HandledMessage[] */
    private array $handledMessages = [];

    public function __construct(string $busName)
    {
        $this->busName = $busName;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $time = microtime(true);

        $result = $stack->next()->handle($envelope, $stack);

        $time = microtime(true) - $time;

        $this->handledMessages[] = new HandledMessage(
            $this->getMessageName($envelope),
            round($time * 1000, 3),
            Debugger::dump($envelope->getMessage(), true),
            implode(
                "\n",
                array_map(
                    static function (HandledStamp $stamp): string {
                        return Debugger::dump($stamp->getResult(), true);
                    },
                    $result->all(HandledStamp::class)
                )
            )
        );

        return $result;
    }

    public function getBusName(): string
    {
        return $this->busName;
    }

    /**
     * @return HandledMessage[]
     */
    public function getHandledMessages(): array
    {
        return $this->handledMessages;
    }

    private function getMessageName(Envelope $envelope): string
    {
        $nameParts = explode('\\', get_class($envelope->getMessage()));

        return $nameParts[count($nameParts) - 1];
    }
}
