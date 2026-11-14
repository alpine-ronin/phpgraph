<h1 align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/docs/brand/logo-dark.svg">
    <img src="https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/docs/brand/logo.svg" alt="phpgraph" width="484">
  </picture>
</h1>

<p align="center"><b>A knowledge graph of your PHP codebase, for AI coding agents.</b></p>

<p align="center">
  <a href="https://github.com/alpine-ronin/phpgraph/actions/workflows/ci.yml"><img src="https://github.com/alpine-ronin/phpgraph/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://packagist.org/packages/alpine-ronin/phpgraph"><img src="https://img.shields.io/packagist/v/alpine-ronin/phpgraph" alt="Packagist version"></a>
  <img src="https://img.shields.io/badge/php-%3E%3D8.5-777bb4" alt="PHP 8.5+ to run from source">
  <a href="https://github.com/alpine-ronin/phpgraph/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue" alt="License: MIT"></a>
</p>

<p align="center"><b>English</b> · <a href="https://github.com/alpine-ronin/phpgraph/blob/main/README.fr.md">Français</a></p>

---

A coding agent in a PHP project it does not know searches names with grep, reads file after file, and guesses when
the answer goes through something a name search does not show: an interface, the service container, a message bus,
a route. phpgraph parses the project once and gives the agent the answer from a graph, over MCP, in one call.

<!-- DEMO: same task with and without phpgraph, 60 to 90s -->

No LLM, no network, no embeddings: your code never leaves your machine, and phpgraph parses it without running it.
Every fact says how sure it is (`EXTRACTED`, `INFERRED` or `AMBIGUOUS`), and what phpgraph could not link is reported.

## One measured result

Akeneo PIM (8,416 PHP files), task: *list every class that must change when
`GetCategoryInterface::byCode()` takes a second parameter*. Claude Code 2.1.323 with Sonnet, phpgraph 2.1.0
development branch, 3 runs with phpgraph and 3 without:

| | With phpgraph | Without |
|---|---:|---:|
| Correct answers | 3/3 | 3/3 |
| Tokens per run, median | 86,174 | 174,735 |
| Tokens per run, range | 86,107 to 130,744 | 172,998 to 175,220 |
| Time per run | 12.8 to 17.5 s | 11.6 to 12.7 s |

The same answer for 51 % fewer tokens (median), not faster. This is phpgraph's best impact task, on the largest project; on the
5 other impact tasks, on smaller projects, phpgraph cost from 1 % to 56 % more tokens. Every result, grouped by
version, with the method: [docs/BENCHMARKS.md](docs/BENCHMARKS.md).

## Quick start

**1. Install** the executable (Linux, macOS; it carries its own PHP, none needed):

```bash
curl -fsSL https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/install.sh | sh
```

It checks the SHA-256 and installs to `~/.local/bin`. Windows, Composer, PHAR and Docker:
[docs/INSTALL.md](docs/INSTALL.md).

**2. Connect Claude Code.** From your project directory:

```bash
phpgraph mcp-config claude
```

It prints a `claude mcp add phpgraph -- ...` command with the paths filled in (run it), an `.mcp.json` to share
with the team instead, and a line for `CLAUDE.md` telling the agent to call phpgraph before grep. Codex, Cursor,
Sherpa and other MCP clients: `phpgraph mcp-config codex|cursor|sherpa|json`.

**3. Ask.** The graph is built on the first call, then kept up to date as you edit.

## What you can ask

- **What breaks if I change this method?** `impact_of` lists every dependent, nearest first, callers through the
  interface it implements included, the routes reaching it and the tests to run.
- **How does this feature work?** `outline` gives the classes, the routes running into them, the behaviour read in
  their bodies, the wiring and the tests in one answer, so the agent reads only the files with non-obvious logic.
- **Which handler receives this message, which controller serves this route?** Senders, messages and handlers are
  linked for Symfony Messenger, Laravel, Ecotone, PrestaShop, WordPress hooks and your own buses; routes to their
  controllers, HTTP calls to the routes they reach.
- **Who calls this, in a large or old codebase?** Receivers are typed from return types, docblocks, generics,
  branches and the signatures of `vendor/`, and PHP 4 and 5 syntax is parsed.

## What it is not

- **Not a runtime tool.** It reads code and configuration, never runs them: services built at runtime (compiler
  passes, ids computed at runtime) and dynamic calls (`$this->$name()`, `__call` without `@method`) stay unresolved.
- **Not every framework.** Symfony, Laravel, Ecotone, PrestaShop and WordPress conventions are recognised, plus
  generic ones (handler attributes, `*Handler`, `*Bus`); others get the call graph only. Only PHP files are read.
- **Better with `vendor/`.** Without installed dependencies, call chains stop at the first one.
- **PHP versions.** The project you analyse can be any PHP version, PHP 4 syntax included, and its PHP does not need
  to be installed. phpgraph itself needs no PHP as an executable or in Docker, and PHP 8.5 or newer with Composer or
  the PHAR.

The full list: [docs/LIMITATIONS.md](docs/LIMITATIONS.md).

## Documentation

- [Install](docs/INSTALL.md): every method, every agent
- [MCP tools](docs/MCP-TOOLS.md): the 8 tools, 4 listed by default
- [Command line](docs/CLI.md): `build`, `impact`, `outline`, `check`, `serve` and their options
- [Graph schema and confidence levels](docs/GRAPH.md): relations, `EXTRACTED` / `INFERRED` / `AMBIGUOUS`, how it works
- [Architecture rules](docs/RULES.md): layers, `phpgraph check` in CI, `phpgraph.yaml`
- [Frameworks](docs/FRAMEWORKS.md): what is recognised, microservices, XML action routing
- [Benchmarks](docs/BENCHMARKS.md): agents with and without phpgraph, other code graphs, the measurement corpus
- [Limitations](docs/LIMITATIONS.md) and [FAQ](docs/FAQ.md)
- [Contributing](CONTRIBUTING.md), [code architecture](docs/ARCHITECTURE.md),
  [library providers](docs/PROVIDERS.md), [stability](docs/STABILITY.md), [changelog](CHANGELOG.md)
- [The previous README](docs/ARCHIVE.md), kept unchanged

## License

[MIT](LICENSE).
