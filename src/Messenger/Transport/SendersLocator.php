<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Transport;

use Fmasa\Messenger\Exceptions\SenderNotFound;
use Fmasa\Messenger\MessageTypeResolver;
use Nette\DI\Container;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

final class SendersLocator implements SendersLocatorInterface
{
    public const TAG_SENDER_ALIAS = 'messenger.sender.alias';

    /** @var array<string, string[]> */
    private $messageTypeToSender;

    /** @var Container */
    private $container;

    /**
     * @param array<string, string[]> $messageTypeToSenders
     */
    public function __construct(array $messageTypeToSenders, Container $container)
    {
        $this->messageTypeToSender = $messageTypeToSenders;
        $this->container           = $container;
    }

    /**
     * @inheritDoc
     */
    public function getSenders(Envelope $envelope) : iterable
    {
        foreach (MessageTypeResolver::listTypes($envelope) as $type) {
            foreach ($this->messageTypeToSender[$type] ?? [] as $alias) {
                yield $alias => $this->getSenderByAlias($alias);
            }
        }
    }

    /**
     * @inheritDoc
     */
    private function getSenderByAlias(string $alias) : SenderInterface
    {
        foreach ($this->container->findByTag(self::TAG_SENDER_ALIAS) as $serviceName => $serviceAlias) {
            if ($serviceAlias !== $alias) {
                continue;
            }

            $sender = $this->container->getService($serviceName);
            assert($sender instanceof SenderInterface);

            return $sender;
        }

        throw SenderNotFound::withAlias($alias);
    }
}
