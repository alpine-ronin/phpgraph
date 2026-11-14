#!/bin/sh
# Records one pane of the side-by-side video: Claude Code (claude -p, Sonnet) on the Akeneo impact question, its
# stream shown as it comes (docs/demo/format-stream.py) and recorded with timings by script(1).
#
#   docs/demo/record-agent.sh <work> left|right
#
# <work> holds two copies of the Akeneo checkout, left/ and right/ (without .git), and for the right pane mcp.json,
# phpgraph's server for right/ with a graph built beforehand. Writes <work>/<pane>.log, .timing and .jsonl;
# docs/demo/redact-recording.py then replaces home paths before the recording is replayed by the video tapes.
set -eu
work=$(readlink -f "$1")
pane=$2
demo=$(dirname "$(readlink -f "$0")")
question='In the Category bounded context, the port Akeneo\Category\Domain\Query\GetCategoryInterface::byCode() will take a second required parameter. List every production class (not tests) whose code must change: the interface, its implementations, and the classes calling byCode() on it.'
if [ "$pane" = right ]; then mcp="$work/mcp.json"; extra=mcp__phpgraph; else mcp="$work/none.json"; echo '{"mcpServers":{}}' > "$mcp"; extra=; fi
cd "$work/$pane"
COLUMNS=95 script -q -T "$work/$pane.timing" -c "claude -p '$question' --model sonnet --setting-sources project --strict-mcp-config --mcp-config '$mcp' \
  --tools Bash,Read,Grep,Glob --allowedTools Read Grep Glob 'Bash(grep:*)' 'Bash(rg:*)' 'Bash(find:*)' 'Bash(cat:*)' 'Bash(ls:*)' 'Bash(sed:*)' 'Bash(head:*)' $extra \
  --output-format stream-json --verbose --no-session-persistence 2>'$work/$pane.stderr' \
  | tee '$work/$pane.jsonl' | python3 -I '$demo/format-stream.py' '$work/$pane'" "$work/$pane.log" </dev/null >/dev/null
