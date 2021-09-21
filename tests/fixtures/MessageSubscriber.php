<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

final class MessageSubscriber implements MessageSubscriberInterface
{
    /**
     * @return iterable<string, array{method: string}>
     */
    public static function getHandledMessages(): iterable
    {
        yield Message::class => ['method' => 'handleMessage'];
        yield Message2::class => ['method' => 'handleMessage2'];
    }

    public function handleMessage(Message $message): string
    {
        return 'message result';
    }

    public function handleMessage2(Message2 $message2): string
    {
        return 'message2 result';
    }
}
