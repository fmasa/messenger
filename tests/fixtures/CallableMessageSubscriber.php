<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

final class CallableMessageSubscriber implements MessageSubscriberInterface
{
    /**
     * @return iterable<string, array{method: string}>
     */
    public static function getHandledMessages(): iterable
    {
        yield Message::class;
    }

    public function __invoke(Message $message): string
    {
        return 'result from callable';
    }
}
