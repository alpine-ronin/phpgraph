# Social card (GitHub social preview, 1280x640)

[`docs/assets/social-card.png`](../assets/social-card.png), rendered from HTML by headless Chrome, in the colours
of `docs/brand/social-preview.png`. GitHub has no API for the social preview: upload it under Settings, General,
Social preview.

**Headline**

> A knowledge graph of your PHP codebase, for AI coding agents

**Sub-line**

> Static analysis served over MCP. No LLM, no network.

**Number**

> Akeneo PIM impact task: 86,174 vs 174,735 tokens
> median, 3 runs each, Claude Code + Sonnet; both arms 3/3 correct

Source: [BENCHMARKS.md](../BENCHMARKS.md#impact-without-the-extra-cost-21), batch `20261113-142124`, Claude Code
2.1.323, Sonnet. It is the best impact task for phpgraph (on 5 smaller-project tasks it used 1 % to 56 % more
tokens), so the card keeps "Akeneo PIM impact task" next to the number: the number alone would claim more.

A number without that caveat, if preferred: "0 parse failures on 26,530 PHP files" (the 11 projects of
[`corpus/RESULTS.md`](../../corpus/RESULTS.md), column "Failed").
