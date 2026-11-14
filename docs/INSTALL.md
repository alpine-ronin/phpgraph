# Install

## The executable (no PHP needed)

Linux and macOS on x86_64 and ARM, Windows on x86_64:

```bash
curl -fsSL https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/install.sh | sh                  # latest release
curl -fsSL https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/install.sh | sh -s -- 0.2.0     # a given release
curl -fsSL https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/install.sh | sh -s -- dev-main  # the main branch
```

```powershell
irm https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/install.ps1 | iex                       # Windows
$env:PHPGRAPH_VERSION = "dev-main"; irm https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/install.ps1 | iex
```

The script downloads the binary, checks its SHA-256 and puts it in `~/.local/bin` (Windows: `%LOCALAPPDATA%\Programs\phpgraph`, added to the PATH); `PHPGRAPH_INSTALL_DIR` changes the directory. Run it again to update. `dev-main` is rebuilt on every push to `main` (the `edge` pre-release). The binary is a static PHP runtime with phpgraph inside, built with [static-php-cli](https://github.com/crazywhalecc/static-php-cli).

## With PHP 8.5 or newer, or with Docker

```bash
composer require --dev alpine-ronin/phpgraph       # or: composer global require alpine-ronin/phpgraph
curl -LO https://github.com/alpine-ronin/phpgraph/releases/latest/download/phpgraph.phar
docker pull ghcr.io/alpine-ronin/phpgraph          # nothing else to install
```

`composer require --dev` installs phpgraph into the project you analyse, so that project's Composer platform must
accept PHP 8.5: on a project pinned to an older PHP, use the executable, `composer global require`, the PHAR or Docker.

## Connect your agent

**2. Connect your agent.** `mcp-config` prints a ready-to-paste configuration with the paths filled in (`vendor/bin/phpgraph` when installed with Composer):

```bash
phpgraph mcp-config sherpa    # Sherpa: a `sherpa mcp add` command, one declaration for every project
phpgraph mcp-config claude    # Claude Code: a `claude mcp add` command, or .mcp.json
phpgraph mcp-config codex     # Codex: ~/.codex/config.toml
phpgraph mcp-config cursor    # Cursor: .cursor/mcp.json, shareable with the team
phpgraph mcp-config json      # any other MCP client over stdio
```

Agents reach for grep first, even with phpgraph connected. `mcp-config` also prints a line for the instructions file of the project (`CLAUDE.md`, `AGENTS.md`) telling the agent to call phpgraph before grep or reading files; the MCP instructions of the server say it too.

With [Sherpa](https://github.com/hbenabdallah/sherpa), a terminal coding agent, one declaration serves every project: Sherpa starts its MCP servers from the project it runs in.

```bash
sherpa mcp add phpgraph -- ~/.local/bin/phpgraph serve
```

In a Sherpa session, `/mcp add` does the same, and the tools are there at once. Both write `~/.config/sherpa/mcp.json`.

With Claude Code (absolute paths, as `mcp-config` prints them):

```bash
claude mcp add phpgraph -- /home/you/.local/bin/phpgraph serve /path/to/project
```

## How releases are built

Releases are published by pushing a version tag: the [release workflow](https://github.com/alpine-ronin/phpgraph/blob/main/.github/workflows/release.yml) builds the PHAR, the executables for Linux, macOS and Windows (`tools/build-binary.sh`), and the Docker image `ghcr.io/alpine-ronin/phpgraph`.
