<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use Doctrine\DBAL\Statement;

/**
 * @internal
 */
final class RetryStrategyConfig
{
    /** @var int */
    public $maxRetries = 3;
    
    /** @var int */
    public $delay = 1000;
    
    /** @var int */
    public $multiplier = 1000;
    
    /** @var int */
    public $maxDelay = 0;
    
    /** @var Statement|string|null */
    public $service = null;
}
