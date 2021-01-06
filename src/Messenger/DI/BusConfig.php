<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use Nette\DI\Statement;

/**
 * @internal
 */
final class BusConfig
{
    public bool $allowNoHandlers = false;

    public bool $singleHandlerPerMessage = false;

    /** @var Statement[] */
    public array $middleware = [];

    public bool $panel = true;
}
