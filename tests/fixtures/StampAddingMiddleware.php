<?php

declare(strict_types=1);

namespace Fixtures;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class StampAddingMiddleware implements MiddlewareInterface
{
    private StampInterface $stamp;

    public function __construct(StampInterface $stamp)
    {
        $this->stamp = $stamp;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $stack->next()->handle($envelope->with($this->stamp), $stack);
    }
}
