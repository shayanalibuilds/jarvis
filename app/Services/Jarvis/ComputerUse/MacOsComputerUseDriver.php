<?php

declare(strict_types=1);

namespace App\Services\Jarvis\ComputerUse;

use App\Exceptions\Jarvis\ComputerUseException;

/**
 * Controls a macOS desktop using screencapture, osascript, and cliclick.
 */
final class MacOsComputerUseDriver extends AbstractProcessDriver
{
    public function name(): string
    {
        return 'macos';
    }

    public function supports(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    public function screenshot(string $destination): string
    {
        $this->run(['screencapture', '-x', $destination], 'screenshot');

        return $destination;
    }

    public function move(int $x, int $y): void
    {
        if (! $this->which('cliclick')) {
            throw ComputerUseException::unsupported('move', $this->name());
        }

        $this->run(['cliclick', "m:{$x},{$y}"], 'move');
    }

    public function click(int $x, int $y, string $button = 'left', int $clicks = 1): void
    {
        if ($this->which('cliclick')) {
            $prefix = match ($button) {
                'right' => 'rc:',
                'middle' => 'mc:',
                default => $clicks > 1 ? 'cc:' : 'c:',
            };

            $this->run(['cliclick', "{$prefix}{$x},{$y}"], 'click');

            return;
        }

        if ($button !== 'left' || $clicks > 1) {
            throw ComputerUseException::unsupported('click', $this->name());
        }

        $this->run([
            'osascript',
            '-e',
            "tell application \"System Events\" to click at {{$x}, {$y}}",
        ], 'click');
    }

    public function type(string $text): void
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $text);

        $this->run(['osascript', '-e', "tell application \"System Events\" to keystroke \"{$escaped}\""], 'type');
    }

    public function pressKey(string $key): void
    {
        [$modifiers, $keystroke] = $this->parseKey($key);

        $using = $modifiers === [] ? '' : ' using {'.implode(', ', $modifiers).' down}';

        $this->run([
            'osascript',
            '-e',
            "tell application \"System Events\" to keystroke \"{$keystroke}\"{$using}",
        ], 'pressKey');
    }

    public function scroll(int $amount): void
    {
        throw ComputerUseException::unsupported('scroll', $this->name());
    }

    public function openApplication(string $application): void
    {
        $this->run(['open', '-a', $application], 'openApplication');
    }

    /**
     * Parse a key combination such as "cmd+shift+s" into AppleScript modifiers and a keystroke.
     *
     * @return array{0: list<string>, 1: string}
     */
    private function parseKey(string $key): array
    {
        $parts = array_filter(explode('+', mb_strtolower($key)));
        $keystroke = (string) array_pop($parts);

        $modifiers = [];

        foreach ($parts as $part) {
            $modifier = match (mb_trim($part)) {
                'cmd', 'command', 'super', 'meta' => 'command',
                'ctrl', 'control' => 'control',
                'alt', 'opt', 'option' => 'option',
                'shift' => 'shift',
                default => null,
            };

            if ($modifier !== null) {
                $modifiers[] = $modifier;
            }
        }

        return [$modifiers, $keystroke];
    }
}
