<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

final class MessageSubscriberWithPriorityOption implements MessageSubscriberInterface
{
    /**
     * @return iterable<string, array{method: string, priority: int}>
     */
    public static function getHandledMessages(): iterable
    {
        yield Message::class => [
            'method' => 'handleMessage',
            'priority' => 10,
        ];
    }

    public function handleMessage(Message $message): string
    {
        return 'message with higher priority result';
    }
}
