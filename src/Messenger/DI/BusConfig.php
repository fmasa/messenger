<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use Nette\DI\Statement;

/**
 * @internal
 */
final class BusConfig
{
    /** @var bool */
    public $allowNoHandlers = false;

    /** @var bool */
    public $singleHandlerPerMessage = false;

    /** @var Statement[] */
    public $middleware = [];

    /** @var bool */
    public $panel = true;
}
