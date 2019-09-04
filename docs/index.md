# Fmasa/Messenger

The usage is nearly identical to Symfony implementation. There is diffrence in convention - this integration uses camelCase everywhere instead of snake_case.

Register extension in your config file using:
```yaml
extensions:
    messenger: Fmasa\Messenger\DI\MessengerExtension
```

## Buses
By default there is no default bus. You can define your named buses as follows:

```yaml
messenger:
    buses:
      commandBus:
      eventBus:
```

This will create services `@messenger.commandBus.bus` and `@messenger.eventBus.bus` of type `MessageBusInterface` in your container. You can [wrap those using custom class](./wrappers.md) to provide autowiring.

## Middleware
By default only registered middleware is the one that passes message to handlers.

There are more of these you might want to use. You can register them like via `middleware` option:
```
messenger:
    buses:
        commandBus:
            middleware:
                - MyCustomMiddleware()
                - @registeredServiceMiddleware
```

See more about middleware in [Symfony docs](https://symfony.com/doc/current/messenger.html#middleware).

## Handlers
Valid handler is callable class with `__invoke()` method. The invoke method musts take exactly one required parameter with class/interface typehint.

Extension automatically registers all services implementing `Symfony\Component\Messenger\Handler\MessageHandlerInterface` or using tag `messenger.messageHandler` as message handlers (this tag is
used for all additional handler configuration.

Handlers are by default registered to all buses. This can be changed via `bus` option. In this example, `RegisterUserHandler` will be registered only to bus called `commandBus`:

```yaml
messenger:
    buses:
        commandBus:
        queryBus:

services:
    - class: RegisterUserHandler
      tags:
          messenger.messageHandler:
              bus: commandBus
```

### Handler aliases
By default different services of same class are treated as unique handlers. In this case, both handler `@a` and `@b` will be called:

```yaml
services:
    a:
        class: RegisterUserHandler
        tags: [messenger.messageHandler]
    b:
        class: RegisterUserHandler
        tags: [messenger.messageHandler]
```

This can be changed using handler aliases. Now only **first** defined handler will be called.

```yaml
    a:
        class: RegisterUserHandler
        tags: [messenger.messageHandler]
    b:
        class: RegisterUserHandler
        tags:
            messenger.messageHandler:
                alias: a # Default value is current service name
```

Your aliases don't need to match service names, these can be arbitrary strings.


## Debugger panel
The extension automatically registers panel to [Tracy](https://tracy.nette.org/) bar.
This allows you to see all handled messages in current request with responses.

Debugger can be disabled on per-bus basis:
```yaml
messenger:
    buses:
        commandBus: # Has panel
        queryBus: # Does not have panel
            panel: false
```

## Recommended usage
- [Message bus wrappers](./wrappers.md)
