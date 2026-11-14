# Replaces home paths in a script(1) recording and rewrites its timing file to the new chunk sizes.
# Usage, from the repository root: python3 -I docs/demo/redact-recording.py <log> <timing> <output prefix>
import os
import sys

# Run from the repository root: the agent may name it in a command.
repository = os.getcwd().encode()
home = os.path.expanduser('~').encode()
log, timing, out = sys.argv[1], sys.argv[2], sys.argv[3]
data = open(log, 'rb').read()
nl = data.index(b'\n') + 1  # "Script started" header line
pos = nl
outlog = [data[:nl]]
outt = []
for line in open(timing):
    d, n = line.split()
    chunk = data[pos:pos + int(n)]
    pos += int(n)
    chunk = chunk.replace(repository, b'~/project').replace(home, b'~')
    outlog.append(chunk)
    outt.append(f'{d} {len(chunk)}\n')
outlog.append(data[pos:])
open(out + '.log', 'wb').write(b''.join(outlog))
open(out + '.timing', 'w').write(''.join(outt))
