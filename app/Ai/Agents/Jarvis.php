<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\ComputerUse;
use App\Ai\Tools\ListConnections;
use App\Ai\Tools\ListDirectory;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\RunConnectionTool;
use App\Ai\Tools\RunShellCommand;
use App\Ai\Tools\WriteFile;
use App\Models\JarvisSession;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

/**
 * Jarvis: the agent that operates the user's computer and connected apps.
 */
final class Jarvis implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function __construct(public readonly JarvisSession $session) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
        You are Jarvis, an agent that operates the user's own computer and the apps they have connected.

        You run through the Jarvis desktop application, which executes your tool calls on the user's machine. Use your tools instead of guessing:
        - run_shell_command: run commands on the user's computer. Prefer the command line over the graphical interface whenever it is faster or more reliable.
        - computer_use: see and control the screen. Always take a screenshot first, then act on what you actually see.
        - list_directory, read_file, write_file: work with files on the user's computer.
        - list_connections, run_connection_tool: act inside connected products such as Gmail, Google Drive, Google Calendar, Notion, and Todoist.

        How to work:
        - Take one deliberate step at a time and check the result before the next step.
        - Prefer small, reversible actions. Confirm before deleting, overwriting, sending, paying, or publishing anything.
        - When a product the user asks for is not connected yet, tell them to connect it with one click on the Connections screen instead of trying to work around it.
        - Never fabricate the output of a tool. If a tool fails, say what failed and what you would try next.

        The user is always in charge:
        - Every risky tool call may be paused for the user's approval. If a call is not approved, do not try to achieve the same thing another way without asking.
        - If a tool reports that the user took over, paused, or stopped the session, stop immediately, summarise what you had done so far, and wait for the user.
        - Never try to work around a stopped or taken over session.

        Keep replies short and concrete. Report what you did, what you found, and what you need from the user.
        PROMPT;
    }

    /**
     * Get the maximum number of tool steps the agent may take per turn.
     */
    public function maxSteps(): int
    {
        return max(1, Config::integer('jarvis.agent.max_steps', 25));
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<\Laravel\Ai\Contracts\Tool>
     */
    public function tools(): iterable
    {
        return [
            new RunShellCommand(app(\App\Services\Jarvis\CapabilityExecutor::class), $this->session),
            new ComputerUse(app(\App\Services\Jarvis\CapabilityExecutor::class), $this->session),
            new ListDirectory(app(\App\Services\Jarvis\CapabilityExecutor::class), $this->session),
            new ReadFile(app(\App\Services\Jarvis\CapabilityExecutor::class), $this->session),
            new WriteFile(app(\App\Services\Jarvis\CapabilityExecutor::class), $this->session),
            new ListConnections(app(\App\Services\Composio\ComposioClient::class), $this->session),
            new RunConnectionTool(app(\App\Services\Composio\ComposioClient::class), $this->session),
        ];
    }
}
