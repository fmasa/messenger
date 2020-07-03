<?php

declare(strict_types=1);

namespace Fmasa\Messenger\DI;

use Nette\DI\Definitions\Statement;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

/**
 * @internal
 */
final class SerializerConfig
{
    /** @var string|Statement */
    public $defaultSerializer = PhpSerializer::class;
}
