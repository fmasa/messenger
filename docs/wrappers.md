# Message bus wrappers

Since all buses are instances of same class `Symfony\Component\Messenger\MessageBus` using these in your services can be cumbersome. The best way to make autowiring work is to create custom wrappers:

```php
<?php

declare(strict_types=1);

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CommandBus
{
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function handle(object $command) : void
    {
        $this->bus->dispatch($command);
    }
}

final class QueryBus
{
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
     * @return mixed
     */
    public function handle(object $query)
    {
        return $this->bus->dispatch($query)->last(HandledStamp::class)->getResult();
    }
}
```

Now you can register these services and typehint against then in your app:

```yaml
- CommandBus(@messenger.commandBus.bus)
- QueryBus(@messenger.queryBus.bus)
```
