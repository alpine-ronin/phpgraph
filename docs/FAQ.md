# FAQ

**Does phpgraph send my code anywhere?** No. It runs locally, makes no network call and uses no LLM.

**Does it execute my code?** No. It parses PHP files and reads JSON, YAML and XML configuration. Even `autoload_classmap.php` and PHP service configurators are parsed, not included.

**Which PHP versions can it analyse?** Anything `nikic/php-parser` 5 parses: PHP 7 and 8 syntax, and the PHP 4 and 5 syntax removed since (`$s{0}`, `=& new`, octals like `08`), for a file the newest grammar rejects. The project's own PHP does not need to be installed: its files are parsed, never run.

**Which PHP does phpgraph itself need?** None with the executable from `install.sh` (it carries its own PHP) or with the Docker image. PHP 8.5 or newer with Composer or the PHAR. `composer require --dev` adds phpgraph to the analysed project, so it fails on a project whose Composer platform is older than PHP 8.5: use the executable or `composer global require` there.

**Which agents does it work with?** Any MCP client over stdio: [Sherpa](https://github.com/hbenabdallah/sherpa), Claude Code, Codex, Cursor, Claude Desktop, and others. `mcp-config` prints the configuration for the common ones.

**How large a project can it handle?** The largest project of the corpus, Akeneo, has 8,416 PHP files: 9.8 s and 896 MB for a full build in the last corpus measurement ([`corpus/RESULTS.md`](../corpus/RESULTS.md); build time varies about ×2 between runs), about 3 s for a rebuild after an edit.
