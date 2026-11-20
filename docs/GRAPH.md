# Graph schema and confidence levels

## Relations

| Relation | From → to |
|---|---|
| `defines`, `imports` | file → class, `use` statement |
| `extends`, `implements`, `uses_trait` | class → parent, interface, trait |
| `has_method`, `overrides` | class → method, method → the method it overrides |
| `instantiates`, `references` | `new Foo()`; parameter, return and property types, `catch`, `instanceof`, constants, attributes |
| `calls` | method → resolved method, with the lines of the call sites; a method running a closure it receives (`$apply(...)`) calls what the closure calls, INFERRED, `via` `closure of <the method writing it>` |
| `dispatches`, `handled_by` | sender → message or channel → handler; route → controller; class tested by a `supports()` method → that strategy |
| `contract` | a message class sent by one service → the same class handled by another |
| `requests` | HTTP call → the route it reaches, in the same service or another, or the operation of another API's OpenAPI document |
| `implemented_by` | API schema operation (`operation:`) → the route with its method and path (OpenAPI), or the method of a class implementing the generated service (protobuf) |
| `receives` | service → each service the container configuration injects into it (a tag, an id, the decorated service); `via` says which: `service sales.pipeline` for one of several services of a class |
| `autowires` | service → an interface or class it takes in more constructor parameters than the configuration gives it services of: the container autowires the others, so `impact` keeps it as a caller whatever it is given, INFERRED |
| `relates_to` | model method → the model of the Eloquent relation it declares (`Book::pages()` returning `$this->hasMany(Page::class)` → `Page`), `via` the relation method (`hasMany`, `belongsTo`…); only when the class is an Eloquent model, EXTRACTED |
| `reads_state_of` | method → a method of the same class changing a property it reads, outside the constructor (`hasErrors()` → `add()`), INFERRED |

## Confidence levels

Every relation carries a confidence level:

| Level | Meaning |
|---|---|
| `EXTRACTED` | Read in the code of one file, or in the configuration (attribute, service tag, route file). |
| `INFERRED` | Resolved across files from declared types: parameters, properties, `$this`, inheritance, return types, `instanceof` tests, shape of the code. A variable that branches leave holding one of several classes links a call to the method of each. |
| `AMBIGUOUS` | A guess, for example the only method of the project with that name, called on a receiver of unknown type; or the method of a class a variable may hold, when another branch leaves it of unknown type. |

## How it works

1. Every PHP file is parsed into facts: declarations, calls with the expression typing their receiver, message sends and handlers, routes, HTTP calls.
2. The builder resolves those facts across files: types through inheritance and return types, `vendor/` signatures read on demand from Composer's metadata (parsed, never executed), service definitions from the container configuration.
3. The graph is saved as `phpgraph-out/graph.json`, with `GRAPH_REPORT.md` for humans.

Rebuilds are incremental: a file's extraction is cached by content, and the MCP server re-resolves only the calls of changed files when no declaration changed anywhere. The result is identical to a full build.

What stays stable across versions (commands, MCP tools, the `graph.json` format, the JSON of `impact` and `outline`) is listed in [STABILITY.md](STABILITY.md), with [the JSON schemas](schemas/).
