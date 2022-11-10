<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

final class MessageSubscriberWithFromTransportOption implements MessageSubscriberInterface
{
    /**
     * @return iterable<string, array{method: string, from_transport: string}>
     */
    public static function getHandledMessages(): iterable
    {
        yield Message::class => [
            'method' => 'handleMessageFromMemory1Transport',
            'from_transport' => 'memory1',
        ];
    }

    public function handleMessageFromMemory1Transport(Message $message): string
    {
        return 'message from memory1 transport result';
    }
}
