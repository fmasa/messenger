<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class DummySerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope) : Envelope
    {
        return new Envelope(new \stdClass());
    }

    public function encode(Envelope $envelope) : array
    {
        return [
            'headers' => '',
            'body' => '',
        ];
    }
}
