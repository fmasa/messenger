<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class CustomTransport implements TransportInterface
{
    /** @var object[] */
    private $sentMessages = [];

    /**
     * @return Envelope[]
     */
    public function get() : iterable
    {
        return [];
    }

    public function ack(Envelope $envelope) : void
    {
    }

    public function reject(Envelope $envelope) : void
    {
    }

    public function send(Envelope $envelope) : Envelope
    {
        $this->sentMessages[] = $envelope->getMessage();

        return $envelope;
    }

    /**
     * @return Envelope[]
     */
    public function getSentMessages() : array
    {
        return $this->sentMessages;
    }
}
