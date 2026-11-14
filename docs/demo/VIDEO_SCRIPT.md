# Side-by-side video

[`docs/assets/demo-video.mp4`](../assets/demo-video.mp4) (68 s, 1920x1080, 0.9 MB) and
[`docs/assets/demo-side-by-side.gif`](../assets/demo-side-by-side.gif) (960 px, 1.1 MB): the same question to Claude
Code with Sonnet on Akeneo PIM, without phpgraph on the left, with it on the right. Each pane replays a real run
(14 November 2026, Claude Code 2.1.325) at its recorded speed; nothing is staged.

## The question

The Akeneo impact task of the benchmark (`tools/agent-bench/tasks/akeneo-impact-category-by-code.yaml`), as written:

> In the Category bounded context, the port Akeneo\Category\Domain\Query\GetCategoryInterface::byCode() will take a
> second required parameter. List every production class (not tests) whose code must change: the interface, its
> implementations, and the classes calling byCode() on it.

No benchmark task was found where the run without phpgraph missed a class that the run with it found: the failures
without phpgraph were namespaces guessed from the path, with the right classes. The video does not claim one: both
answers name the 7 expected classes.

## What the recording shows

| | Without phpgraph | With phpgraph |
|---|---:|---:|
| Expected classes named | 7 of 7 | 7 of 7 |
| Tokens (input + cache creation + cache read + output) | 59,553 | 53,478 |
| Tool calls | 6 (all Grep) | 4 (`impact_of`, then a shell command and 2 Grep) |
| Time | 22.1 s | 16.3 s |
| Cost reported by Claude Code (list price) | $0.07 | $0.06 |

One run each: a direction, not a measure. The results card of the video puts it next to the benchmark (3 runs per
arm, Claude Code 2.1.323): median tokens 174,735 without and 86,174 with, time not better (11.6 to 12.7 s without,
12.8 to 17.5 s with), and on 5 smaller-project impact tasks phpgraph used 1 % to 56 % more tokens
([BENCHMARKS.md](../BENCHMARKS.md#impact-without-the-extra-cost-21)).

Differences from the benchmark: the answer is free text (no `--json-schema`), the tools are Read, Grep, Glob and
read-only shell commands (`grep`, `rg`, `find`, `cat`, `ls`, `sed`, `head`) instead of Bash, Edit and Write with
permissions skipped, and the runs are on the host, not in the benchmark's container.

## Timeline

| Time | Shows | Caption |
|---|---|---|
| 0 to 4 s | title card | Same question, same model, same repository |
| 4 to 11 s | the question | The question |
| 11 to 23 s | both panes, tool calls as they come | Same question in both panes |
| 23 to 39 s | both panes | Left: grep only. Right: graph query first |
| 39 to 50 s | both answers and their token counts | Both answers list the same 7 classes |
| 50 to 62 s | results card: this recording, then the benchmark | The numbers |
| 62 to 68 s | end card | Install phpgraph, the install command, github.com/alpine-ronin/phpgraph |

## Rebuilding it

Needs Claude Code logged in, phpgraph, [vhs](https://github.com/charmbracelet/vhs) with `ttyd` and `ffmpeg`, and
`google-chrome`; from the repository root:

```bash
work=build/demo/akeneo && mkdir -p $work
cp -r corpus/checkouts/akeneo-pim $work/left && rm -rf $work/left/.git $work/left/phpgraph-out
cp -r $work/left $work/right
phpgraph build $work/right -o $work/graph       # outside the recording
printf '{"mcpServers":{"phpgraph":{"command":"%s","args":["serve","%s","-o","%s"]}}}\n' \
  "$(command -v phpgraph)" "$(readlink -f $work/right)" "$(readlink -f $work/graph)" > $work/mcp.json
docs/demo/record-agent.sh $work left & docs/demo/record-agent.sh $work right & wait   # 2 agent runs, about $0.15
for p in left right; do python3 -I docs/demo/redact-recording.py $work/$p.log $work/$p.timing docs/demo/recordings/$p; done
vhs docs/demo/video-left.tape && vhs docs/demo/video-right.tape                        # one at a time
docs/demo/make-video.sh
```

A new recording gives other numbers: update the results card in `make-video.sh` and the table above from the last
line of each pane.
