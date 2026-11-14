# Command line

```bash
phpgraph build [path]                        # phpgraph-out/graph.json and GRAPH_REPORT.md
phpgraph overview                            # stack, structure and gaps
phpgraph impact "OrderRepository::save"      # what depends on it, and the tests to run (--all, --section tests, --format json)
phpgraph check                               # layer rules, for CI: exit code 1 on a new violation
phpgraph explain "PlaceOrderHandler"         # a node and its relations (-d in|out)
phpgraph path "StockChecker" "DbalOrderRepository"
phpgraph query "how is stock checked"
phpgraph outline "notification validation"   # the outline of a feature (--section flow, --format json)
phpgraph serve [path]                        # the MCP server, over stdio
phpgraph mcp-config claude|codex|cursor|json [--docker image]
```

`build` options: `-e` to exclude paths (repeatable), `--no-vendor` not to read dependencies, `--no-cache` to parse every file again. `vendor`, `node_modules`, `var`, `.git` and the files ignored by the project's own `.gitignore` files are skipped; a git repository above the project never applies its rules, so a project copied into an ignored directory is still read. When the directory holds PHP files but every one is excluded, `build` fails and says so, and every MCP answer starts with that warning instead of serving an empty graph. Memory is unlimited by default; `PHPGRAPH_MEMORY_LIMIT=2G` sets a limit. The files are parsed by one worker process per processor, with PHP's `pcntl` extension (in the binaries and the Docker image; not on Windows, where they are parsed one after the other): Sylius builds in 10 s instead of 28 s on a 12-thread laptop. `PHPGRAPH_WORKERS=4` sets the number of workers, `PHPGRAPH_WORKERS=1` parses in one process. With PHP installed, opcache and its JIT make builds about 20% faster: `php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=128M vendor/bin/phpgraph build` (the Docker image and the commands printed by `mcp-config` already use them).
