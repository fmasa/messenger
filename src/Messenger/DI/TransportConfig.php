<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

/**
 * @internal
 */
final class TransportConfig
{
    /** @var string */
    public $dsn;
    
    /** @var RetryStrategyConfig|null */
    public $retryStrategy;

    /** @var mixed[] */
    public $options = [];
}
