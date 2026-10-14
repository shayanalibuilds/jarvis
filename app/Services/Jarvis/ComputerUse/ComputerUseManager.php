<?php

declare(strict_types=1);

namespace App\Services\Jarvis\ComputerUse;

use App\Exceptions\Jarvis\ComputerUseException;

/**
 * Resolves the computer use driver for the current machine and stores screenshots.
 */
final class ComputerUseManager
{
    public function __construct(private readonly ?ComputerUseDriver $driver = null) {}

    /**
     * Resolve the driver that should handle computer use actions.
     */
    public function driver(): ComputerUseDriver
    {
        if ($this->driver instanceof ComputerUseDriver) {
            return $this->driver;
        }

        if (! (bool) config('jarvis.computer_use.enabled', true)) {
            return new NullComputerUseDriver;
        }

        $configured = (string) config('jarvis.computer_use.driver', 'auto');

        if ($configured !== 'auto') {
            return $this->driverNamed($configured);
        }

        return $this->detectDriver();
    }

    /**
     * Determine whether computer use is available on this machine.
     */
    public function isAvailable(): bool
    {
        return $this->driver()->supports();
    }

    /**
     * Capture a screenshot and return the path it was stored at.
     */
    public function screenshot(string $directory = 'jarvis/screenshots'): string
    {
        $driver = $this->driver();

        if (! $driver->supports()) {
            throw ComputerUseException::unsupported('screenshot', $driver->name());
        }

        $relativePath = mb_trim($directory, '/').'/'.now()->format('Ymd-His').'-'.mb_substr((string) str()->uuid(), 0, 8).'.png';
        $absolutePath = storage_path('app/'.$relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        $driver->screenshot($absolutePath);

        return $relativePath;
    }

    /**
     * Resolve a driver by name.
     */
    private function driverNamed(string $name): ComputerUseDriver
    {
        return match ($name) {
            'macos' => new MacOsComputerUseDriver,
            'linux' => new LinuxComputerUseDriver,
            'windows' => new WindowsComputerUseDriver,
            default => new NullComputerUseDriver,
        };
    }

    /**
     * Detect the driver that fits the current operating system.
     */
    private function detectDriver(): ComputerUseDriver
    {
        foreach ([new MacOsComputerUseDriver, new LinuxComputerUseDriver, new WindowsComputerUseDriver] as $driver) {
            if ($driver->supports()) {
                return $driver;
            }
        }

        return new NullComputerUseDriver;
    }
}
