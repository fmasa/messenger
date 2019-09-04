# Message bus wrappers

Since all buses are instances of same class `Symfony\Component\Messenger\MessageBus` using these in your services can be cumbersome. The best way to make autowiring work is to create custom wrappers:

```php
<?php

declare(strict_types=1);

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CommandBus
{
    /** @var MessageBusInterface */
    private $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
     * @param object $command
     */
    public function handle($command) : void
    {
        $this->bus->dispatch($command);
    }
}

final class QueryBus
{
    /** @var MessageBusInterface */
    private $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
     * @param object $command
     *
     * @return mixed
     */
    public function handle($command)
    {
        return $this->bus->dispatch($command)->last(HandledStamp::class)->getResult();
    }
}
```

Now you can register these services and typehint against then in your app:

```yaml
- CommandBus(@messenger.commandBus.bus)
- QueryBus(@messenger.queryBus.bus)
```
