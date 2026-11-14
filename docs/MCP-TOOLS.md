# MCP tools

| Tool | Use it to |
|---|---|
| `overview` | Start here: Composer stack, namespace tree with layers, bounded contexts, layer-rule violations, messages, HTTP routes (each with method, path, controller and file up to 30, else grouped by path prefix), services, and what the graph cannot see. |
| `query_graph` | Find the code about a topic when you do not know the class names, by the words of their names (validated finds `Validator`), with the classes of the feature around the ones it names. A question about routes (*"mooc courses routes"*, *"GET /courses"*) returns the matching routes and their controllers. The families of classes around the answer are counted by folder (*ContextRuleInterface: 34 in …*), injected lists once (*ContextValidator receives 34 ContextRuleInterface*). |
| `outline` | Before explaining a feature: its structural outline, enough to read only the few files with non-obvious logic. The classes it is made of, by namespace and layer, with the first sentence of their docblock, their public signatures, constants and enum cases; the interfaces and base classes around it with their implementations by module (0 stated); the routes running into it, chain by chain, through the closures it runs; behaviour read in the bodies (guarded throws, early returns, branches on constants, loop caps, state compared before and after); the container wiring with service ids; its users, tests counted by module, what no test touches and what nothing uses. About 15 KB; a list cut short names the section that has it all (`section behaviour`, 1 to 9 KB), and a core full at 45 classes names those it left out. A class used by many (the `User` model) brings nothing in with it: it shows the methods the feature calls and its attributes naming the feature's classes (`$log: Log`). A method (`OrderService::place`) gives what it runs instead: the methods it calls, 3 calls deep, each class with those methods only; hubs (called by 20 classes or more, a singleton's `getInstance()`) counted, not followed; its behaviour nearest first. |
| `get_node` | Read one class, method, route or channel with all its relations; tests using it are counted by module. |
| `get_neighbors` | See what uses a node (`in`) or what it depends on (`out`). |
| `impact_of` | Before a change: every class that depends on a class or method, nearest first, with the lines of the calls; callers through the interface it implements; code depending on the state it writes; the routes reaching it, with each chain; and the tests to run, found through test helpers. It flags the classes no test touches and those nothing in the application uses. |
| `shortest_path` | See how two pieces of code are connected. |
| `god_nodes` | Find the hubs most of the code depends on. |

The server lists `outline`, `impact_of`, `get_node` and `get_neighbors`: an agent reads the list again on every turn, and in the [benchmark](BENCHMARKS.md) it never called the other four. `serve --all-tools` lists them too. A path, as an agent sees it, on [php-ddd-example](https://github.com/CodelyTV/php-ddd-example):

```text
Shortest path (4 hops):
  PUT /courses/{id} --handled_by--> CoursesPutController::__invoke()
    --dispatches--> CreateCourseCommand --handled_by--> CreateCourseCommandHandler::__invoke()
    --calls--> CourseCreator::__invoke()
```
