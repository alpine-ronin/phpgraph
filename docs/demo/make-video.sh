#!/bin/sh
# Builds the side-by-side video (docs/assets/demo-video.mp4) and its GIF (docs/assets/demo-side-by-side.gif)
# from the two pane videos rendered by vhs docs/demo/video-left.tape and docs/demo/video-right.tape.
# Needs google-chrome (cards and captions) and ffmpeg. Run from the repository root.
set -eu

out=build/demo
cards=$out/cards
mkdir -p "$cards"

style='html,body{margin:0;background:#1e1f29;color:#e6e8ee;font-family:"DejaVu Sans",sans-serif}
.card{width:1920px;height:1080px;box-sizing:border-box;padding:120px 160px;display:flex;flex-direction:column;justify-content:center}
h1{font-size:64px;margin:0 0 32px}p{font-size:34px;line-height:1.45;color:#b6bccb;margin:0 0 20px}
code,pre{font-family:"DejaVu Sans Mono",monospace}pre{font-size:30px;color:#e6e8ee;white-space:pre-wrap;margin:0 0 28px}
table{font-size:36px;border-collapse:collapse;margin:0 0 36px}td,th{padding:10px 40px 10px 0;text-align:left}th{color:#9a9fe6}
.small{font-size:26px;color:#8a90a0}
.band{width:1920px;height:120px;display:flex;align-items:center;justify-content:center;font-size:44px;font-weight:700;background:#13161d}'

card() { # name, body
    printf '<!doctype html><html><head><meta charset="utf-8"><style>%s</style></head><body>%s</body></html>' "$style" "$2" > "$cards/$1.html"
    google-chrome --headless=new --disable-gpu --no-sandbox --hide-scrollbars --window-size="$3" \
        --screenshot="$cards/$1.png" "file://$PWD/$cards/$1.html" 2>/dev/null
}

card title '<div class="card"><h1>Same question, same model, same repository</h1>
<p>Claude Code with Sonnet on Akeneo PIM (8,416 PHP files): without phpgraph on the left, with it on the right.</p>
<p class="small">One real run each, replayed at its recorded speed. Tools: Read, Grep, Glob and read-only shell commands (grep, find, cat, sed...), plus phpgraph on the right.</p></div>' 1920,1080

card question '<div class="card"><h1>The question</h1>
<pre>In the Category bounded context, the port
Akeneo\Category\Domain\Query\GetCategoryInterface::byCode()
will take a second required parameter. List every production class
(not tests) whose code must change: the interface, its implementations,
and the classes calling byCode() on it.</pre>
<p class="small">The Akeneo impact task of the benchmark: tools/agent-bench/tasks/akeneo-impact-category-by-code.yaml</p></div>' 1920,1080

card results '<div class="card"><h1>The numbers</h1>
<p>This recording, one run each (Claude Code 2.1.325, Sonnet):</p>
<table><tr><th></th><th>Without</th><th>With phpgraph</th></tr>
<tr><td>Classes found</td><td>7 of 7</td><td>7 of 7</td></tr>
<tr><td>Tokens</td><td>59,553</td><td>53,478</td></tr>
<tr><td>Tool calls</td><td>6</td><td>4</td></tr>
<tr><td>Time</td><td>22.1 s</td><td>16.3 s</td></tr></table>
<p>Benchmark, 3 runs per arm (Claude Code 2.1.323): median tokens 174,735 without, 86,174 with; time not better
(11.6 to 12.7 s without, 12.8 to 17.5 s with).</p>
<p class="small">On 5 smaller-project impact tasks, phpgraph used 1 % to 56 % more tokens. docs/BENCHMARKS.md</p></div>' 1920,1080

card end '<div class="card" style="align-items:center;text-align:center"><h1>Install phpgraph</h1>
<pre>curl -fsSL https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/install.sh | sh</pre>
<p>github.com/alpine-ronin/phpgraph</p></div>' 1920,1080

card caption1 '<div class="band">Same question in both panes</div>' 1920,120
card caption2 '<div class="band">Left: grep only. Right: graph query first</div>' 1920,120
card caption3 '<div class="band">Both answers list the same 7 classes</div>' 1920,120

ffmpeg -loglevel error -y \
    -loop 1 -t 4 -i "$cards/title.png" \
    -loop 1 -t 7 -i "$cards/question.png" \
    -i "$out/video-left.mp4" -i "$out/video-right.mp4" \
    -loop 1 -i "$cards/caption1.png" -loop 1 -i "$cards/caption2.png" -loop 1 -i "$cards/caption3.png" \
    -loop 1 -t 12 -i "$cards/results.png" \
    -loop 1 -t 6 -i "$cards/end.png" \
    -filter_complex "
      [2:v]tpad=stop_mode=clone:stop_duration=5,trim=duration=39,setpts=PTS-STARTPTS,fps=25[l];
      [3:v]tpad=stop_mode=clone:stop_duration=5,trim=duration=39,setpts=PTS-STARTPTS,fps=25[r];
      [l][r]hstack,drawbox=x=958:y=0:w=4:h=960:color=0x44475a:t=fill[panes];
      [4:v]trim=duration=39,setpts=PTS-STARTPTS[k1];[5:v]trim=duration=39,setpts=PTS-STARTPTS[k2];[6:v]trim=duration=39,setpts=PTS-STARTPTS[k3];
      [k1][k2]overlay=enable='gte(t,12)'[k12];[k12][k3]overlay=enable='gte(t,28)'[band];
      [band][panes]vstack,fps=25,format=yuv420p,setsar=1[mid];
      [0:v]fps=25,format=yuv420p,setsar=1[a];[1:v]fps=25,format=yuv420p,setsar=1[b];
      [7:v]fps=25,format=yuv420p,setsar=1[c];[8:v]fps=25,format=yuv420p,setsar=1[d];
      [a][b][mid][c][d]concat=n=5:v=1:a=0[v]" \
    -map "[v]" -c:v libx264 -crf 30 -preset slow -movflags +faststart docs/assets/demo-video.mp4

ffmpeg -loglevel error -y -i docs/assets/demo-video.mp4 \
    -vf "fps=8,scale=960:-1:flags=lanczos,split[s0][s1];[s0]palettegen=max_colors=64[p];[s1][p]paletteuse=dither=none" \
    docs/assets/demo-side-by-side.gif
