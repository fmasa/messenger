<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use function strpos;

final class CustomTransportFactory implements TransportFactoryInterface
{
    /** @var CustomTransport */
    private $transport;

    public function __construct(CustomTransport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * @param mixed[] $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer) : TransportInterface
    {
        return $this->transport;
    }

    /**
     * @param mixed[] $options
     */
    public function supports(string $dsn, array $options) : bool
    {
        return strpos($dsn, 'custom://') === 0;
    }
}
