<?php

declare(strict_types=1);

namespace App\Services\Jarvis\ComputerUse;

use App\Exceptions\Jarvis\ComputerUseException;

/**
 * Controls a Linux desktop using xdotool and a screenshot utility.
 */
final class LinuxComputerUseDriver extends AbstractProcessDriver
{
    public function name(): string
    {
        return 'linux';
    }

    public function supports(): bool
    {
        return PHP_OS_FAMILY === 'Linux' && $this->which('xdotool');
    }

    public function screenshot(string $destination): string
    {
        $binary = $this->firstAvailable(['scrot', 'gnome-screenshot', 'import', 'xfce4-screenshooter']);

        if ($binary === null) {
            throw ComputerUseException::unsupported('screenshot', $this->name());
        }

        $command = match ($binary) {
            'scrot' => ['scrot', '-o', $destination],
            'gnome-screenshot' => ['gnome-screenshot', '-f', $destination],
            'xfce4-screenshooter' => ['xfce4-screenshooter', '-f', '-s', $destination],
            default => ['import', '-window', 'root', $destination],
        };

        $this->run($command, 'screenshot');

        return $destination;
    }

    public function move(int $x, int $y): void
    {
        $this->run(['xdotool', 'mousemove', '--sync', (string) $x, (string) $y], 'move');
    }

    public function click(int $x, int $y, string $button = 'left', int $clicks = 1): void
    {
        $this->move($x, $y);

        $this->run([
            'xdotool',
            'click',
            '--repeat',
            (string) max(1, $clicks),
            (string) $this->buttonNumber($button),
        ], 'click');
    }

    public function type(string $text): void
    {
        $this->run(['xdotool', 'type', '--delay', '12', '--', $text], 'type');
    }

    public function pressKey(string $key): void
    {
        $this->run(['xdotool', 'key', '--', $this->normalizeKey($key)], 'pressKey');
    }

    public function scroll(int $amount): void
    {
        $button = $amount >= 0 ? '4' : '5';

        $this->run(['xdotool', 'click', '--repeat', (string) max(1, abs($amount)), $button], 'scroll');
    }

    public function openApplication(string $application): void
    {
        $this->run(['xdg-open', $application], 'openApplication');
    }

    /**
     * Map a button name to the xdotool button number.
     */
    private function buttonNumber(string $button): int
    {
        return match ($button) {
            'middle' => 2,
            'right' => 3,
            default => 1,
        };
    }

    /**
     * Normalize a key combination for xdotool.
     */
    private function normalizeKey(string $key): string
    {
        return mb_strtolower(str_replace(['+', 'cmd', 'command', 'control'], ['+', 'super', 'super', 'ctrl'], $key));
    }
}
