# Prints a Claude Code stream-json run as it comes: tool calls, the answer, then tokens, tool calls and time.
# Usage: claude -p ... --output-format stream-json --verbose | python3 -I format-stream.py <project root to hide>
import json, sys, time
root = sys.argv[1]
start = time.time()
calls = 0
def clean(s):
    return str(s).replace(root + '/', '').replace(root, '.')
for line in sys.stdin:
    try:
        e = json.loads(line)
    except ValueError:
        continue
    if e.get('type') == 'assistant':
        for c in e.get('message', {}).get('content', []):
            if c.get('type') != 'tool_use':
                continue
            calls += 1
            name = c['name'].replace('mcp__phpgraph__', 'phpgraph ')
            i = c.get('input', {})
            arg = i.get('command') or i.get('pattern') or i.get('file_path') or json.dumps(i)
            arg = clean(arg).replace('\n', ' ')
            print(f'\033[36m● {name}\033[0m {arg[:150]}', flush=True)
    elif e.get('type') == 'result':
        u = e.get('usage', {})
        tokens = sum(u.get(k, 0) for k in ('input_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens', 'output_tokens'))
        print('\n' + clean(e.get('result', '')), flush=True)
        print(f'\n\033[33mtokens {tokens:,}   tool calls {calls}   time {time.time() - start:.1f} s\033[0m', flush=True)
