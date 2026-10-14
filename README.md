<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://github.com/user-attachments/assets/7f3c77b9-e549-4887-872e-a0d512678945">
    <source media="(prefers-color-scheme: light)" srcset="https://github.com/user-attachments/assets/8cf172b8-0e36-47c4-b096-a6fad0044e32">
    <img alt="Fission Logo" src="https://github.com/user-attachments/assets/fd074588-4ffd-47f3-af6e-a24500ecbc55">
  </picture>
</p>

> [!IMPORTANT]
> This is an opinionated starter kit created by me (Josh Cirre) using Laravel Livewire and Livewire Flux. While PRs are welcome, this is designed to fit the needs of one person.

> [!TIP]
> Clone the repository and run `composer setup` to get started quickly. See [Installation](#installation) below.

## TweakFlux — Deep Theming for Flux UI

Fission pairs well with [**TweakFlux**](https://github.com/joshcirre/tweakflux), a theming package that lets you transform every Flux component with a single command. Override Tailwind v4 CSS custom properties to apply 20+ preset themes or generate your own — zero vendor files touched.

```bash
composer global require joshcirre/tweakflux
tweakflux apply bubblegum
```

<p align="center">
  <img src="art/tweakflux-preview.jpeg" alt="TweakFlux Bubblegum theme preview" width="700">
</p>

## Why Does This Exist?

Up until Livewire Flux released, I used Breeze as a starting point for 99% of new projects that I would create. Typically, those new projects were built for demos on videos or starting points for tutorials. In addition, I would start side projects or app ideas with Breeze, as well.

Eventually I knew I wanted to create my own starting kit that worked well for what I needed in most scenarios. Authentication and a dashboard where I can start writing code.

Once Livewire Flux released, it was the perfect time to make this happen.

## Flux

Fission uses [Livewire Flux](https://fluxui.dev) (free) for its UI components. No Flux Pro license is required out of the box.

If you want access to premium components (date pickers, calendars, charts, tabs, and more), you can install Flux Pro during setup when prompted for optional packages. Fission does not include any of Flux's CSS or built assets — you must have the package installed to use it.

## Installation

### Quick Start (Recommended)

```bash
git clone https://github.com/joshcirre/fission.git my-project
cd my-project
composer setup
```

### Using Composer Create-Project

```bash
composer create-project joshcirre/fission my-project
cd my-project
composer setup
```

> [!NOTE]
> The `laravel new --using` flag is not recommended due to archive extraction issues with special characters in filenames.

The `composer setup` command handles:

- Dependency installation
- Environment configuration (.env)
- Application key generation
- SQLite database creation
- Optional packages (Flux Pro, Filament, Bento, etc.)
- Optional teams scaffolding (defaults to no during install)
- Database migrations
- Project name configuration
- NPM dependency installation
- Asset building

During installation, Fission will ask whether you want full teams support.

- Choose `yes` to keep team creation, switching, invitations, and member role management.
- Choose `no` to remove the team-specific backend, routes, views, and tests before setup finishes.

### Working With Teams

When teams are enabled, Fission follows the starter-kit pattern for team-aware features:

- Store the active team on the user via `current_team_id` and switch context from the team switcher UI.
- Put team-owned features under a team URL when the page itself is team-specific, for example `/{team}/playground`.
- Model team-owned records with a `team_id` foreign key and query through the bound team, not globally.
- Authorize access through team membership or team policies, for example `->can('view', 'team')` on routes.
- Keep generic account pages like `/profile` unscoped unless the page is explicitly about a team.

## Development

```bash
composer dev          # Start server, queue, logs, and Vite
```

### Code Quality

Fission enforces strict code quality through automated tooling:

```bash
composer fix          # Fix everything: types, refactoring, formatting
composer test         # Run all checks: tests, linting, types, refactoring
```

| Command             | Purpose                                              |
| ------------------- | ---------------------------------------------------- |
| `composer fix`      | PHPStan → Rector → Prettier → Pint                   |
| `composer test`     | Typos → Pest → Lint check → PHPStan → Rector dry-run |
| `composer lint`     | Pint + Prettier (quick format)                       |
| `composer refactor` | Rector only                                          |

### Individual Test Commands

```bash
composer test:unit          # Pest tests (parallel)
composer test:unit:coverage # Pest with coverage
composer test:types         # PHPStan analysis
composer test:lint          # Check formatting (no fix)
composer test:refactor      # Rector dry-run
composer test:typos         # Peck typo checker
```

### Tooling Stack

- **[Pest](https://pestphp.com)** - Testing framework
- **[PHPStan](https://phpstan.org)** + Larastan - Static analysis (max level)
- **[Rector](https://getrector.com)** - Automated refactoring
- **[Pint](https://laravel.com/docs/pint)** - PHP code style (strict Laravel)
- **[Prettier](https://prettier.io)** - JS/CSS formatting
- **[Peck](https://github.com/peckphp/peck)** - Typo detection

## Recommended AI Skills

If you use [AI coding assistants](https://skills.sh) with this project, these skills provide useful context for the stack:

| Skill | Install |
|-------|---------|
| **Flux UI Development** — Flux UI component usage, variants, and patterns | `npx skills add laravel/boost --skill fluxui-development` |
| **Livewire Development** — Livewire 4 component patterns, directives, islands, and testing | `npx skills add spatie/freek.dev@livewire-development` |
| **TweakFlux Theme Generator** — Generate custom Flux UI themes from descriptions or palettes | `tweakflux boost` (requires [joshcirre/tweakflux](https://github.com/joshcirre/tweakflux)) |
| **Playwriter** — Control your actual Chrome browser from AI agents (requires [Chrome extension](https://playwriter.dev)) | Included in `.agents/skills/` · CLI: `npm i -g playwriter` |
| **Expect CLI** — Adversarial browser testing for code changes (requires `npm i -g expect-cli`) | Included in `.agents/skills/` · Init: `npx -y expect-cli@latest init` |

## Jarvis

Jarvis is an agent that operates your computer and the products you connect to it. It is built on the
[Laravel AI SDK](https://laravel.com/docs/ai-sdk), ships as a desktop application (the primary experience) plus a web
console, and can always be taken over or stopped by you with a shortcut key.

### What it can do

| Capability | Description |
| --- | --- |
| `run_shell_command` | Run shell commands on your computer and read the output. |
| `computer_use` | See the screen and drive the mouse and keyboard: screenshot, move, click, type, keys, scroll, open apps. |
| `list_directory`, `read_file`, `write_file` | Work with files on your computer. |
| `list_connections`, `run_connection_tool` | Act inside connected products: Gmail, Google Drive, Google Calendar, Google Docs, Google Sheets, Notion, Todoist, Slack, Linear, GitHub, and more. |

### You are always in control

Every Jarvis session is stored in `jarvis_sessions` and can be paused, resumed, taken over, or stopped:

| Shortcut | Action |
| --- | --- |
| `Cmd/Ctrl + Shift + J` | **Take over** - Jarvis stops acting immediately and hands control back to you. |
| `Cmd/Ctrl + Shift + Space` | Pause or resume. |
| `Cmd/Ctrl + Shift + Backspace` | Stop the session. |
| `Escape` | Take over (inside the web console). |

Taking over cancels every tool call that is in flight on your desktop, so nothing keeps running behind your back. On
top of that, risky tool calls can require your explicit approval before they execute
(`JARVIS_REQUIRE_APPROVAL`), commands matching `jarvis.shell.blocked_patterns` are always refused, and writes to
`jarvis.filesystem.protected_paths` are blocked even after approval.

### Setup

1. Add an AI provider key (any provider supported by the AI SDK, OpenAI by default):

```bash
OPENAI_API_KEY=sk-...
# or ANTHROPIC_API_KEY=, GEMINI_API_KEY=, ...
```

2. Add a [Composio](https://composio.dev) API key to enable one-step connections:

```bash
COMPOSIO_API_KEY=...
```

3. Migrate and open the console:

```bash
php artisan migrate
composer dev
```

- **Web console:** `/jarvis` - prompt Jarvis, watch the transcript, approve tool calls, and take over.
- **Connections:** `/jarvis/connections` - click **Connect** next to Gmail, Drive, Notion, Todoist, and more. You are
  sent to the provider once, and the connection is done.

### Desktop application

The desktop app is the main way to run Jarvis: it loads the console, registers the global shortcuts above, lives in
your tray, and executes every tool call on your own machine.

```bash
cd desktop
npm install
npm start
```

- Set `JARVIS_URL` to point at your Jarvis instance (defaults to `http://localhost:8000`).
- Sign in inside the window: the app pairs itself with your account automatically and stores its device token in
  `jarvis.json` under the app's user data directory.
- The app polls `GET /api/jarvis/devices/next`, executes the capability locally, and reports the result back to
  `POST /api/jarvis/devices/calls/{call}`.

### Where commands run

`JARVIS_SHELL_TARGET` decides where capabilities execute:

| Value | Behaviour |
| --- | --- |
| `auto` (default) | Use the connected desktop device when one is online, otherwise run on the server hosting Jarvis. |
| `device` | Only ever run on the connected desktop device. |
| `local` | Always run on the machine hosting Jarvis. |

### Tests

```bash
php artisan test --compact   # Laravel feature tests, including the take-over, device API, and connection flows
cd desktop && npm test       # Desktop executor and device agent tests (node:test)
```

## License

The Fission starter kit is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
