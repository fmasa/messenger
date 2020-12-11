<?php

declare(strict_types=1);

namespace Fixtures;

use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class DummySerializer implements SerializerInterface
{
    /**
     * @param mixed[] $encodedEnvelope
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        return new Envelope(new stdClass());
    }

    /**
     * @return array<string, string>
     */
    public function encode(Envelope $envelope): array
    {
        return [
            'headers' => '',
            'body' => '',
        ];
    }
}
