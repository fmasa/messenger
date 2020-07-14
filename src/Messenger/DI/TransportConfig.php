<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use Nette\DI\Definitions\Statement;

/**
 * @internal
 */
final class TransportConfig
{
    /** @var string */
    public $dsn;

    /** @var mixed[] */
    public $options = [];

    /**
     * Service/class used as serializer for given transport. When null is passed, default serializer will be used.
     *
     * @var string|Statement|null
     */
    public $serializer = null;
}
