<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

final class MessageSubscriberWithBusOption implements MessageSubscriberInterface
{
    /**
     * @return iterable<string, array{method: string, bus: string}>
     */
    public static function getHandledMessages(): iterable
    {
        yield Message::class => [
            'method' => 'handleMessage',
            'bus' => 'other',
        ];
    }

    public function handleMessage(Message $message): string
    {
        return 'message with bus option result';
    }
}
