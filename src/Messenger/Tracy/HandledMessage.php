<?php

declare(strict_types=1);

namespace Fmasa\Messenger\Tracy;

final class HandledMessage
{
    /** @var string */
    private $messageName;

    /** @var float */
    private $timeInMs;

    /** @var string */
    private $messageDump;

    /** @var string */
    private $resultDump;

    public function __construct(string $messageName, float $timeInMs, string $messageDump, string $resultDump)
    {
        $this->messageName = $messageName;
        $this->timeInMs = $timeInMs;
        $this->messageDump = $messageDump;
        $this->resultDump = $resultDump;
    }

    public function getMessageName() : string
    {
        return $this->messageName;
    }

    public function getTimeInMs() : float
    {
        return $this->timeInMs;
    }

    public function getMessageDump() : string
    {
        return $this->messageDump;
    }

    public function getResultDump() : string
    {
        return $this->resultDump;
    }
}
