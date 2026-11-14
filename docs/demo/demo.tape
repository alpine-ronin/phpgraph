# The README GIF: mcp-config, a build and one impact query on php-ddd-example.
#
# Run from the repository root, with phpgraph installed and the corpus fetched (composer corpus:fetch):
#   vhs docs/demo/demo.tape
# The checkout is copied to /tmp so that the paths printed by mcp-config do not show your home directory,
# nor the binary's: it is copied to /tmp/phpgraph-demo-bin.

Output docs/assets/demo.gif

Set Shell "bash"
Set Width 1100
Set Height 600
Set FontSize 16
Set Theme "Dracula"
Set TypingSpeed 20ms
Set Padding 20

Hide
Type "rm -rf /tmp/php-ddd-example && cp -r corpus/checkouts/php-ddd-example /tmp/php-ddd-example && rm -rf /tmp/php-ddd-example/phpgraph-out" Enter
Sleep 6s
Type `mkdir -p /tmp/phpgraph-demo-bin && cp "$(command -v phpgraph)" /tmp/phpgraph-demo-bin/ && export PATH=/tmp/phpgraph-demo-bin:$PATH` Enter
Sleep 1s
Type "cd /tmp/php-ddd-example && export PS1='$ ' && clear" Enter
Sleep 500ms
Show

# 1. Connect the agent (install already done)
Type "phpgraph mcp-config claude" Sleep 300ms Enter
Sleep 5s
Type "clear" Enter

# 2. Build the graph
Type "phpgraph build" Sleep 300ms Enter
Wait+Screen@60s /edges/
Sleep 4s
Type "clear" Enter

# 3. What breaks if this method changes
Type `phpgraph impact "CourseCreator::__invoke" | head -15` Sleep 300ms Enter
Sleep 8s
