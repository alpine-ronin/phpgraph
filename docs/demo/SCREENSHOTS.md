# Screenshots

Three PNG files in `docs/assets/`, taken by [`shots.tape`](shots.tape) from real command output (vhs, 1600 px wide,
font 15, Dracula theme), then cropped to their content:

```bash
vhs docs/demo/shots.tape      # writes the three full screens to docs/assets/shot-N.png
for s in "1 604" "2 99" "3 100"; do set -- $s
  mv docs/assets/shot-$1.png build/demo/shot-$1-full.png
  ffmpeg -y -i build/demo/shot-$1-full.png -vf "crop=1600:$2:0:0" docs/assets/shot-$1.png
done
```

## `shot-1.png`: an agent answer built from the graph

The right pane of the [side-by-side video](VIDEO_SCRIPT.md), replayed from `docs/demo/recordings/right.log`: Claude
Code with phpgraph on the Akeneo impact question, its `impact_of` call, the 7 classes and its token count (53,478, one
run).

## `shot-2.png`: tokens and tool calls side by side

The benchmark's own table for the batch the README quotes, from the runs recorded in `build/agent-bench/` (kept
locally, not in git):

```bash
tools/agent-bench/bench report build/agent-bench/results/20261113-142124.jsonl | grep -E '^\| (Task \| Kind|akeneo-impact)'
```

The report gives means with their standard deviation (101,008 ± 25,752 tokens with phpgraph, 174,318 ± 1,168
without), 1.3 against 3.7 tool calls, 18 against 13 s; the README quotes medians (86,174 and 174,735). Do not caption
the screenshot with the README's numbers.

## `shot-3.png`: a path through the graph

php-ddd-example, from an HTTP route to the application service, through the command bus:

```bash
cd corpus/checkouts/php-ddd-example
phpgraph path "PUT /courses/{id}" "CourseCreator::__invoke"
```
