# One pane of the side-by-side video: replays the real recording docs/demo/recordings/left.log at its recorded speed.
# Recorded with docs/demo/record-agent.sh, home paths masked by docs/demo/redact-recording.py; run from the repository root: vhs docs/demo/video-left.tape

Output build/demo/video-left.mp4

Set Shell "bash"
Set Width 960
Set Height 960
Set FontSize 18
Set Theme "Dracula"
Set Padding 24

Hide
Type "export PS1='' && clear" Enter
Sleep 500ms
Type "clear; printf '\033[1mWITHOUT phpgraph\033[0m\n\n'; scriptreplay -t docs/demo/recordings/left.timing docs/demo/recordings/left.log" Enter
Show
Sleep 2s
Sleep 24s
Sleep 14s
