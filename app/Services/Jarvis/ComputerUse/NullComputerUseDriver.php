<?php

declare(strict_types=1);

namespace App\Services\Jarvis\ComputerUse;

use App\Exceptions\Jarvis\ComputerUseException;

/**
 * A driver used when computer use is disabled or unsupported.
 */
final class NullComputerUseDriver extends AbstractProcessDriver
{
    public function name(): string
    {
        return 'null';
    }

    public function supports(): bool
    {
        return false;
    }

    public function screenshot(string $destination): string
    {
        throw ComputerUseException::unsupported('screenshot', $this->name());
    }

    public function move(int $x, int $y): void
    {
        throw ComputerUseException::unsupported('move', $this->name());
    }

    public function click(int $x, int $y, string $button = 'left', int $clicks = 1): void
    {
        throw ComputerUseException::unsupported('click', $this->name());
    }

    public function type(string $text): void
    {
        throw ComputerUseException::unsupported('type', $this->name());
    }

    public function pressKey(string $key): void
    {
        throw ComputerUseException::unsupported('pressKey', $this->name());
    }

    public function scroll(int $amount): void
    {
        throw ComputerUseException::unsupported('scroll', $this->name());
    }

    public function openApplication(string $application): void
    {
        throw ComputerUseException::unsupported('openApplication', $this->name());
    }
}
