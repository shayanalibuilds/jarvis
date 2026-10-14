<?php

declare(strict_types=1);

use App\Ai\Tools\ComputerUse;
use App\Ai\Tools\ListDirectory;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\RunShellCommand;
use App\Ai\Tools\WriteFile;
use App\Exceptions\Jarvis\JarvisInterruptedException;
use App\Models\JarvisSession;
use App\Services\Jarvis\CapabilityExecutor;
use App\Services\Jarvis\ComputerUse\ComputerUseManager;
use App\Services\Jarvis\ComputerUse\ComputerUseDriver;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    config()->set('jarvis.shell.target', 'local');
});

test('the shell tool runs a command and returns its output', function (): void {
    $session = JarvisSession::factory()->running()->create();

    $tool = new RunShellCommand(app(CapabilityExecutor::class), $session);

    $result = json_decode($tool->handle(new Request(['command' => 'echo jarvis-online'])), true);

    expect($result['successful'])->toBeTrue()
        ->and($result['output'])->toBe('jarvis-online')
        ->and($result['exit_code'])->toBe(0)
        ->and($result['target'])->toBe('local');
});

test('the shell tool refuses permanently blocked commands', function (): void {
    $session = JarvisSession::factory()->running()->create();

    $tool = new RunShellCommand(app(CapabilityExecutor::class), $session);

    $result = json_decode($tool->handle(new Request(['command' => 'rm -rf /'])), true);

    expect($result['successful'])->toBeFalse()
        ->and($result['error'])->toContain('permanently blocked');
});

test('file tools read, list, and write files', function (): void {
    $session = JarvisSession::factory()->running()->create();
    $directory = sys_get_temp_dir().'/jarvis-'.uniqid();

    $write = new WriteFile(app(CapabilityExecutor::class), $session);
    $written = json_decode($write->handle(new Request([
        'path' => $directory.'/notes.txt',
        'contents' => 'hello from jarvis',
    ])), true);

    expect($written['bytes'])->toBe(17);

    $read = new ReadFile(app(CapabilityExecutor::class), $session);
    $contents = json_decode($read->handle(new Request(['path' => $directory.'/notes.txt'])), true);

    expect($contents['contents'])->toBe('hello from jarvis');

    $list = new ListDirectory(app(CapabilityExecutor::class), $session);
    $listed = json_decode($list->handle(new Request(['path' => $directory])), true);

    expect($listed['entries'])->toHaveCount(1)
        ->and($listed['entries'][0]['name'])->toBe('notes.txt');

    unlink($directory.'/notes.txt');
    rmdir($directory);
});

test('file tools refuse to write to protected paths', function (): void {
    $session = JarvisSession::factory()->running()->create();

    $tool = new WriteFile(app(CapabilityExecutor::class), $session);

    $result = json_decode($tool->handle(new Request([
        'path' => '/etc/jarvis.conf',
        'contents' => 'nope',
    ])), true);

    expect($result['successful'])->toBeFalse()
        ->and($result['error'])->toContain('blocked');
});

test('tools refuse to run once the user has taken over', function (): void {
    $session = JarvisSession::factory()->takenOver()->create();

    $tool = new RunShellCommand(app(CapabilityExecutor::class), $session);

    expect(fn () => $tool->handle(new Request(['command' => 'echo nope'])))
        ->toThrow(JarvisInterruptedException::class);
});

test('the computer use tool reports the active driver', function (): void {
    $session = JarvisSession::factory()->running()->create();

    $driver = new class implements ComputerUseDriver
    {
        public function name(): string
        {
            return 'fake';
        }

        public function supports(): bool
        {
            return true;
        }

        public function screenshot(string $destination): string
        {
            return $destination;
        }

        public function move(int $x, int $y): void {}

        public function click(int $x, int $y, string $button = 'left', int $clicks = 1): void {}

        public function type(string $text): void {}

        public function pressKey(string $key): void {}

        public function scroll(int $amount): void {}

        public function openApplication(string $application): void {}
    };

    $tool = new ComputerUse(new CapabilityExecutor(
        app(\App\Services\Jarvis\DeviceBridge::class),
        app(\App\Services\Jarvis\Shell\CommandRunner::class),
        new ComputerUseManager($driver),
    ), $session);

    $result = json_decode($tool->handle(new Request(['action' => 'click', 'x' => 10, 'y' => 20])), true);

    expect($result['driver'])->toBe('fake')
        ->and($result['action'])->toBe('click')
        ->and($result['capability'])->toBe('computer_use')
        ->and($result['output'])->toBe(['ok' => true]);
});

test('risky tools require approval when configured', function (): void {
    $session = JarvisSession::factory()->running()->create();

    config()->set('jarvis.control.require_approval', true);
    $shell = new RunShellCommand(app(CapabilityExecutor::class), $session);
    expect($shell->shouldRequestApproval(new Request(['command' => 'ls'])))->not->toBeNull();

    config()->set('jarvis.control.require_approval', false);
    $relaxed = new RunShellCommand(app(CapabilityExecutor::class), $session);
    expect($relaxed->shouldRequestApproval(new Request(['command' => 'ls'])))->toBeNull();
});
