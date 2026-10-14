<?php

declare(strict_types=1);

namespace App\Services\Jarvis\ComputerUse;

/**
 * Controls a Windows desktop using PowerShell and .NET.
 */
final class WindowsComputerUseDriver extends AbstractProcessDriver
{
    public function name(): string
    {
        return 'windows';
    }

    public function supports(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public function screenshot(string $destination): string
    {
        $script = 'Add-Type -AssemblyName System.Windows.Forms,System.Drawing; '
            .'$b=[System.Windows.Forms.SystemInformation]::VirtualScreen; '
            .'$bmp=New-Object System.Drawing.Bitmap $b.Width,$b.Height; '
            .'$g=[System.Drawing.Graphics]::FromImage($bmp); '
            .'$g.CopyFromScreen($b.Left,$b.Top,0,0,$bmp.Size); '
            .'$bmp.Save('.escapeshellarg($destination).',[System.Drawing.Imaging.ImageFormat]::Png)';

        $this->run(['powershell', '-NoProfile', '-Command', $script], 'screenshot');

        return $destination;
    }

    public function move(int $x, int $y): void
    {
        $this->mouseEvent('move', $x, $y, 0);
    }

    public function click(int $x, int $y, string $button = 'left', int $clicks = 1): void
    {
        $flag = match ($button) {
            'right' => '0x0008',
            'middle' => '0x0020',
            default => '0x0002',
        };

        $this->mouseEvent('click', $x, $y, $flag, max(1, $clicks));
    }

    public function type(string $text): void
    {
        $escaped = str_replace(["'", '+', '^', '%', '~', '(', ')', '[', ']', '{', '}'], ' ', $text);

        $this->run([
            'powershell',
            '-NoProfile',
            '-Command',
            "Add-Type -AssemblyName System.Windows.Forms; [System.Windows.Forms.SendKeys]::SendWait('{$escaped}')",
        ], 'type');
    }

    public function pressKey(string $key): void
    {
        $combination = $this->sendKeysCombination($key);

        $this->run([
            'powershell',
            '-NoProfile',
            '-Command',
            "Add-Type -AssemblyName System.Windows.Forms; [System.Windows.Forms.SendKeys]::SendWait('{$combination}')",
        ], 'pressKey');
    }

    public function scroll(int $amount): void
    {
        $this->mouseEvent('scroll', 0, 0, $amount >= 0 ? '0x00000800' : '0x00000800', 1, $amount * 120);
    }

    public function openApplication(string $application): void
    {
        $this->run(['powershell', '-NoProfile', '-Command', "Start-Process '{$application}'"], 'openApplication');
    }

    /**
     * Translate a key combination such as "ctrl+shift+s" into SendKeys syntax.
     */
    private function sendKeysCombination(string $key): string
    {
        $parts = array_filter(explode('+', mb_strtolower($key)));
        $keystroke = (string) array_pop($parts);

        $prefix = '';

        foreach ($parts as $part) {
            $prefix .= match (mb_trim($part)) {
                'ctrl', 'control', 'cmd', 'command', 'super', 'meta' => '^',
                'alt', 'opt', 'option' => '%',
                'shift' => '+',
                default => '',
            };
        }

        return $prefix.$keystroke;
    }

    /**
     * Invoke a Win32 mouse event through PowerShell.
     */
    private function mouseEvent(string $action, int $x, int $y, string|int $flag, int $clicks = 1, int $delta = 0): void
    {
        $script = '$sig = \'[DllImport("user32.dll")] public static extern void mouse_event(uint f, uint dx, uint dy, int d, int e); '
            .'[DllImport("user32.dll")] public static extern bool SetCursorPos(int x, int y);\'; '
            .'$m = Add-Type -MemberDefinition $sig -Name JarvisMouse -Namespace Jarvis -PassThru; '
            ."[Jarvis.JarvisMouse]::SetCursorPos({$x},{$y}); "
            .'1..'.max(1, $clicks).' | ForEach-Object { $m::mouse_event('.$flag.', 0, 0, '.$delta.', 0) }';

        $this->run(['powershell', '-NoProfile', '-Command', $script], $action);
    }
}
