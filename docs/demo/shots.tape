# The three screenshots of docs/demo/SCREENSHOTS.md, from real command output.
# Run from the repository root, with phpgraph installed, the corpus fetched and the benchmark runs of
# build/agent-bench/ present: vhs docs/demo/shots.tape

Output build/demo/shots.gif

Set Shell "bash"
Set Width 1600
Set Height 900
Set FontSize 15
Set Theme "Dracula"
Set TypingSpeed 10ms
Set Padding 24

Hide
Type "export PS1='$ ' && export ROOT=$PWD && clear" Enter
Sleep 500ms
Show

# shot-3: a path through the graph, php-ddd-example
Hide
Type "cd corpus/checkouts/php-ddd-example && clear" Enter
Sleep 500ms
Show
Type `phpgraph path "PUT /courses/{id}" "CourseCreator::__invoke"` Enter
Sleep 4s
Screenshot docs/assets/shot-3.png
Sleep 1s

# shot-2: tokens and tool calls of the batch the README quotes
Hide
Type "cd $ROOT && clear" Enter
Sleep 500ms
Show
Type `tools/agent-bench/bench report build/agent-bench/results/20261113-142124.jsonl | grep -E '^\| (Task \| Kind|akeneo-impact)'` Enter
Wait+Screen@300s /baseline/
Sleep 2s
Screenshot docs/assets/shot-2.png
Sleep 1s

# shot-1: the agent's answer with phpgraph, the recording of the side-by-side video
Hide
Type "clear; printf '\033[1mClaude Code with phpgraph, Akeneo PIM\033[0m\n\n'; scriptreplay -t docs/demo/recordings/right.timing docs/demo/recordings/right.log" Enter
Show
Sleep 20s
Screenshot docs/assets/shot-1.png
Sleep 1s
