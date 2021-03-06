<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

use function strpos;

final class CustomTransportFactory implements TransportFactoryInterface
{
    private CustomTransport $transport;

    private ?string $dns = null;

    /** @var mixed[]|null */
    private ?array $options = null;

    private ?SerializerInterface $serializer = null;

    public function __construct(CustomTransport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * @param mixed[] $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $this->dns        = $dsn;
        $this->options    = $options;
        $this->serializer = $serializer;

        return $this->transport;
    }

    public function getUsedDns(): ?string
    {
        return $this->dns;
    }

    /**
     * @return mixed[]
     */
    public function getUsedOptions(): ?array
    {
        return $this->options;
    }

    public function getUsedSerializer(): ?SerializerInterface
    {
        return $this->serializer;
    }

    /**
     * @param mixed[] $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return strpos($dsn, 'custom://') === 0;
    }
}
