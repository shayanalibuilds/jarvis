<?php

declare(strict_types=1);

namespace App\Services\Jarvis\ComputerUse;

/**
 * A driver that can see and control the desktop of the machine it runs on.
 */
interface ComputerUseDriver
{
    /**
     * Get the driver name.
     */
    public function name(): string;

    /**
     * Determine whether the driver can run on this machine.
     */
    public function supports(): bool;

    /**
     * Capture the screen into the given destination file.
     */
    public function screenshot(string $destination): string;

    /**
     * Move the mouse cursor to the given screen coordinates.
     */
    public function move(int $x, int $y): void;

    /**
     * Click the mouse at the given screen coordinates.
     */
    public function click(int $x, int $y, string $button = 'left', int $clicks = 1): void;

    /**
     * Type the given text using the keyboard.
     */
    public function type(string $text): void;

    /**
     * Press a single key or key combination, for example "enter" or "cmd+s".
     */
    public function pressKey(string $key): void;

    /**
     * Scroll vertically. Positive values scroll up, negative values scroll down.
     */
    public function scroll(int $amount): void;

    /**
     * Open an application or file by name or path.
     */
    public function openApplication(string $application): void;
}
