<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Jarvis Identity
    |--------------------------------------------------------------------------
    |
    | The display name and personality used by the Jarvis agent and surfaced
    | throughout the desktop and web applications.
    |
    */

    'name' => env('JARVIS_NAME', 'Jarvis'),

    'agent' => [
        'provider' => env('JARVIS_PROVIDER'),
        'model' => env('JARVIS_MODEL'),
        'max_steps' => (int) env('JARVIS_MAX_STEPS', 25),
        'timeout' => (int) env('JARVIS_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Control (Take Over / Stop)
    |--------------------------------------------------------------------------
    |
    | Jarvis is never allowed to run unattended. Every session can be paused,
    | resumed, taken over, or stopped by the user. The accelerators below are
    | registered globally by the desktop application and mirrored as keyboard
    | shortcuts inside the web application.
    |
    */

    'control' => [
        'shortcuts' => [
            'take_over' => [
                'accelerator' => env('JARVIS_SHORTCUT_TAKE_OVER', 'CommandOrControl+Shift+J'),
                'keys' => env('JARVIS_SHORTCUT_TAKE_OVER_KEYS', 'ctrl+shift+j'),
                'label' => 'Take over',
            ],
            'pause' => [
                'accelerator' => env('JARVIS_SHORTCUT_PAUSE', 'CommandOrControl+Shift+Space'),
                'keys' => env('JARVIS_SHORTCUT_PAUSE_KEYS', 'ctrl+shift+space'),
                'label' => 'Pause / resume',
            ],
        ],

        'require_approval' => (bool) env('JARVIS_REQUIRE_APPROVAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shell Execution
    |--------------------------------------------------------------------------
    |
    | Jarvis can run shell commands on your computer. Execution happens on the
    | connected desktop device when one is online, and falls back to the host
    | running this application when "local" execution is enabled.
    |
    */

    'shell' => [
        'enabled' => (bool) env('JARVIS_SHELL_ENABLED', true),
        'target' => env('JARVIS_SHELL_TARGET', 'auto'),
        'local_execution' => (bool) env('JARVIS_SHELL_LOCAL_EXECUTION', true),
        'timeout' => (int) env('JARVIS_SHELL_TIMEOUT', 60),
        'working_directory' => env('JARVIS_SHELL_WORKING_DIRECTORY'),
        'max_output_length' => (int) env('JARVIS_SHELL_MAX_OUTPUT', 20000),

        /*
        | Commands matching any of these patterns are always rejected, even
        | after the user approves the tool call.
        */
        'blocked_patterns' => [
            'rm -rf /',
            'mkfs',
            ':(){ :|:& };:',
            'dd if=/dev/zero',
            '> /dev/sda',
            'chmod -R 777 /',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Computer Use
    |--------------------------------------------------------------------------
    |
    | Screenshot, mouse, and keyboard control. The driver is resolved from the
    | operating system of the machine executing the action, or may be set
    | explicitly. Use "null" to disable computer use entirely.
    |
    */

    'computer_use' => [
        'enabled' => (bool) env('JARVIS_COMPUTER_USE_ENABLED', true),
        'driver' => env('JARVIS_COMPUTER_USE_DRIVER', 'auto'),
        'screenshot_path' => env('JARVIS_SCREENSHOT_PATH'),
        'screenshot_disk' => env('JARVIS_SCREENSHOT_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem
    |--------------------------------------------------------------------------
    |
    | Jarvis may read and write files. Writes to the paths below are always
    | refused, even when the user approves the tool call.
    |
    */

    'filesystem' => [
        'protected_paths' => [
            '/etc/',
            '/bin/',
            '/sbin/',
            '/usr/',
            '/boot/',
            '/sys/',
            '/proc/',
            '/var/lib/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Devices (Desktop Application)
    |--------------------------------------------------------------------------
    |
    | The desktop application registers itself as a device and polls for tool
    | calls to execute locally. Devices that stop sending heartbeats are
    | considered offline.
    |
    */

    'devices' => [
        'heartbeat_ttl' => (int) env('JARVIS_DEVICE_HEARTBEAT_TTL', 60),
        'poll_timeout' => (int) env('JARVIS_DEVICE_POLL_TIMEOUT', 30),
        'result_timeout' => (int) env('JARVIS_DEVICE_RESULT_TIMEOUT', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections (Composio)
    |--------------------------------------------------------------------------
    |
    | One-step connections to Google (Drive, Gmail, Calendar, Docs, Sheets),
    | Notion, Todoist, and 100+ other products. Connections are brokered by
    | Composio so Jarvis can act on your behalf without you configuring OAuth
    | clients yourself.
    |
    */

    'composio' => [
        'key' => env('COMPOSIO_API_KEY'),
        'base_url' => env('COMPOSIO_BASE_URL', 'https://backend.composio.dev/api/v3'),
        'timeout' => (int) env('COMPOSIO_TIMEOUT', 30),
        'toolkit_cache_ttl' => (int) env('COMPOSIO_TOOLKIT_CACHE_TTL', 3600),

        /*
        | The connection catalog rendered on the connections screen. Slugs are
        | Composio toolkit slugs and may be extended at will.
        */
        'toolkits' => [
            'google' => [
                'label' => 'Google Workspace',
                'toolkits' => [
                    ['slug' => 'gmail', 'name' => 'Gmail', 'description' => 'Read, search, and send email.'],
                    ['slug' => 'googledrive', 'name' => 'Google Drive', 'description' => 'Find, upload, and organize files.'],
                    ['slug' => 'googlecalendar', 'name' => 'Google Calendar', 'description' => 'Create events and manage your schedule.'],
                    ['slug' => 'googledocs', 'name' => 'Google Docs', 'description' => 'Create and edit documents.'],
                    ['slug' => 'googlesheets', 'name' => 'Google Sheets', 'description' => 'Read and write spreadsheet data.'],
                ],
            ],
            'productivity' => [
                'label' => 'Productivity',
                'toolkits' => [
                    ['slug' => 'notion', 'name' => 'Notion', 'description' => 'Search pages and create notes or databases.'],
                    ['slug' => 'todoist', 'name' => 'Todoist', 'description' => 'Add and complete tasks.'],
                    ['slug' => 'slack', 'name' => 'Slack', 'description' => 'Send messages and read channels.'],
                    ['slug' => 'linear', 'name' => 'Linear', 'description' => 'Create and update issues.'],
                    ['slug' => 'trello', 'name' => 'Trello', 'description' => 'Manage boards, lists, and cards.'],
                    ['slug' => 'asana', 'name' => 'Asana', 'description' => 'Manage projects and tasks.'],
                    ['slug' => 'clickup', 'name' => 'ClickUp', 'description' => 'Manage tasks and docs.'],
                    ['slug' => 'airtable', 'name' => 'Airtable', 'description' => 'Read and write records.'],
                    ['slug' => 'jira', 'name' => 'Jira', 'description' => 'Track issues and sprints.'],
                    ['slug' => 'figma', 'name' => 'Figma', 'description' => 'Inspect designs and files.'],
                ],
            ],
            'communication' => [
                'label' => 'Communication',
                'toolkits' => [
                    ['slug' => 'discord', 'name' => 'Discord', 'description' => 'Send messages and manage servers.'],
                    ['slug' => 'telegram', 'name' => 'Telegram', 'description' => 'Send messages as your bot.'],
                    ['slug' => 'microsoft_teams', 'name' => 'Microsoft Teams', 'description' => 'Send messages and manage channels.'],
                    ['slug' => 'outlook', 'name' => 'Outlook', 'description' => 'Read and send email and calendar invites.'],
                ],
            ],
            'developer' => [
                'label' => 'Developer & Storage',
                'toolkits' => [
                    ['slug' => 'github', 'name' => 'GitHub', 'description' => 'Manage repositories, issues, and pull requests.'],
                    ['slug' => 'dropbox', 'name' => 'Dropbox', 'description' => 'Store and retrieve files.'],
                    ['slug' => 'linkedin', 'name' => 'LinkedIn', 'description' => 'Post and read your profile.'],
                    ['slug' => 'x', 'name' => 'X', 'description' => 'Post and read timelines.'],
                    ['slug' => 'youtube', 'name' => 'YouTube', 'description' => 'Search and manage videos.'],
                ],
            ],
        ],
    ],
];
