<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration

namespace Fmasa\Messenger\DI;

use Nette\DI\Definitions\Statement;

/**
 * @internal
 */
final class TransportConfig
{
    public string $dsn;

    /** @var array<string, mixed> */
    public array $options = [];

    /**
     * Service/class used as serializer for given transport. When null is passed, default serializer will be used.
     *
     * @var string|Statement|null
     */
    public $serializer = null;

    public ?string $failureTransport = null;
}
