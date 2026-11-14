# One pane of the side-by-side video: replays the real recording docs/demo/recordings/right.log at its recorded speed.
# Recorded with docs/demo/record-agent.sh, home paths masked by docs/demo/redact-recording.py; run from the repository root: vhs docs/demo/video-right.tape

Output build/demo/video-right.mp4

Set Shell "bash"
Set Width 960
Set Height 960
Set FontSize 18
Set Theme "Dracula"
Set Padding 24

Hide
Type "export PS1='' && clear" Enter
Sleep 500ms
Type "clear; printf '\033[1mWITH phpgraph (MCP server)\033[0m\n\n'; scriptreplay -t docs/demo/recordings/right.timing docs/demo/recordings/right.log" Enter
Show
Sleep 2s
Sleep 18s
Sleep 20s
